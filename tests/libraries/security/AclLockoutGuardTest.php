<?php

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Security\AclLockoutGuard;
use Gaia\MVC\Models\Role;
use Gaia\MVC\Models\Permission;
use Gaia\MVC\Models\Userrole;
use Gaia\MVC\Models\User;
use Gaia\MVC\REST\Controllers\RoleController;
use Gaia\MVC\REST\Controllers\UserroleController;
use Gaia\MVC\REST\Controllers\PermissionController;
use Gaia\MVC\REST\Controllers\UserController;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Tests the capability-based ACL lockout invariant (D-052): the
 * system must always retain at least one non-deleted role that is
 * "admin-capable" (no explicit deny on permission, role, userrole
 * module actions, or user.{create,update,delete}) and holds at least one
 * usable user_roles membership (Active accountStatus, case-insensitive).
 *
 * Also exercises the controller guards that enforce the invariant on role
 * delete, permission demotion, userrole delete, and user deactivate/delete.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AclLockoutGuardTest extends TestCase
{
    /**
     * @var array<\Phalcon\Mvc\Model> Rows created during a test, deleted in tearDown.
     */
    private $createdModels = [];

    /**
     * Seed roles temporarily denied so systemRetainsAdminPath reflects only
     * fixtures created by the current test.
     *
     * @var array<int, array{model: Permission, previousAllowed: mixed|null, created: bool}>
     */
    private $neutralizedPermissions = [];

    public static function setUpBeforeClass(): void
    {
        global $currentUser;

        self::ensureSharedLockoutUser();
        $currentUser = User::findFirstByEmail('acl-lockout-test@gaia.test');
    }

    public static function tearDownAfterClass(): void
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute("DELETE FROM user_roles WHERE \"userId\" = 'test-user-acl-lockout'");
        $db->execute("DELETE FROM users WHERE id = 'test-user-acl-lockout' OR email = 'acl-lockout-test@gaia.test'");
        $db->execute("DELETE FROM users WHERE email LIKE 'acl-lockout-%@gaia.test'");
    }

    /**
     * Create or revive the shared Active lockout user. Soft-deleted rows for
     * the same email/id block inserts due to unique indexes, so revive via
     * SQL when needed.
     */
    private static function ensureSharedLockoutUser(): void
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $existing = $db->fetchOne(
            "SELECT id FROM users WHERE id = 'test-user-acl-lockout' OR email = 'acl-lockout-test@gaia.test' LIMIT 1"
        );

        if ($existing) {
            $db->execute(
                "UPDATE users
                 SET deleted = 0,
                     \"accountStatus\" = 'Active',
                     email = 'acl-lockout-test@gaia.test',
                     name = 'ACL Lockout Test User'
                 WHERE id = ?"
                ,
                [$existing['id']]
            );
            return;
        }

        $passwordHash = password_hash('unit-testing', PASSWORD_DEFAULT);
        $db->execute(
            "INSERT INTO users (
                id, password, email, name, deleted,
                \"createdUser\", \"modifiedUser\", \"createdUserName\", \"modifiedUserName\",
                \"accountStatus\", \"failedLoginAttempts\"
             ) VALUES (
                'test-user-acl-lockout', ?, 'acl-lockout-test@gaia.test', 'ACL Lockout Test User', 0,
                'test-user-acl-lockout', 'test-user-acl-lockout', 'testUser', 'testUser',
                'Active', 0
             )",
            [$passwordHash]
        );
    }

    protected function setUp(): void
    {
        $this->purgeLockoutTestArtifacts();
        $this->ensureCurrentUserIsActive();
        $this->neutralizedPermissions = [];
        $this->neutralizeExistingCapableRoles();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->createdModels) as $model) {
            $model->delete();
        }
        $this->createdModels = [];
        $this->restoreNeutralizedPermissions();
        $this->purgeLockoutTestArtifacts();
    }

    /**
     * Hard-remove leftover Lockout fixture roles/memberships/permissions so
     * soft-delete pollution cannot skew neutralize / systemRetainsAdminPath.
     */
    private function purgeLockoutTestArtifacts(): void
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute(
            "DELETE FROM permissions WHERE \"roleId\" IN (SELECT id FROM roles WHERE name LIKE 'Lockout%')"
        );
        $db->execute(
            "DELETE FROM user_roles WHERE \"roleId\" IN (SELECT id FROM roles WHERE name LIKE 'Lockout%')"
        );
        $db->execute("DELETE FROM roles WHERE name LIKE 'Lockout%'");
    }

    /**
     * Keep the shared ACL lockout user usable for membership counts.
     */
    private function ensureCurrentUserIsActive(): void
    {
        global $currentUser;

        self::ensureSharedLockoutUser();
        $currentUser = User::findFirstByEmail('acl-lockout-test@gaia.test');

        if (!$currentUser) {
            throw new \RuntimeException('Shared ACL lockout test user is missing after ensureSharedLockoutUser()');
        }
    }

    /**
     * Temporarily deny permission.get on every seeded capable+membered role so
     * "sole capable role" assertions are not skewed by Admin fixtures.
     */
    private function neutralizeExistingCapableRoles(): void
    {
        foreach (Role::find() as $role) {
            if (!AclLockoutGuard::isAdminCapableRole($role->id)) {
                continue;
            }
            if (AclLockoutGuard::roleMemberCount($role->id) < 1) {
                continue;
            }
            $this->forceDenyCapability($role->id);
        }
    }

    /**
     * @param string $roleId
     */
    private function forceDenyCapability($roleId): void
    {
        $resourceName = 'permission.get';
        $existingRows = Permission::find([
            'conditions' => 'roleId = :roleId: AND resourceName = :resourceName:',
            'bind' => [
                'roleId' => $roleId,
                'resourceName' => $resourceName,
            ],
        ]);

        if (count($existingRows) > 0) {
            foreach ($existingRows as $existing) {
                $this->neutralizedPermissions[] = [
                    'model' => $existing,
                    'previousAllowed' => $existing->allowed,
                    'created' => false,
                ];
                $existing->allowed = '0';
                $existing->save();
            }
            return;
        }

        $permission = new Permission();
        $permission->id = create_guid();
        $permission->roleId = $roleId;
        $permission->resourceName = $resourceName;
        $permission->allowed = '0';
        $permission->save();

        $this->neutralizedPermissions[] = [
            'model' => $permission,
            'previousAllowed' => null,
            'created' => true,
        ];
    }

    private function restoreNeutralizedPermissions(): void
    {
        foreach (array_reverse($this->neutralizedPermissions) as $entry) {
            /** @var Permission $permission */
            $permission = $entry['model'];
            if ($entry['created']) {
                $permission->delete();
                continue;
            }
            $permission->allowed = $entry['previousAllowed'];
            $permission->save();
        }
        $this->neutralizedPermissions = [];
    }

    /**
     * A role with no permission rows at all is capable: missing rows allow
     * under the default permissive resolution mode (D-011/D-014).
     */
    public function testMissingRowsAreCapableByDefault(): void
    {
        $role = $this->createRole('Lockout Test Role A');

        $this->assertTrue(AclLockoutGuard::isAdminCapableRole($role->id));
    }

    /**
     * An explicit deny on any one of the fifteen lockout-covered resources
     * removes capability, even if the other fourteen are missing (allowed).
     */
    public function testExplicitDenyMakesRoleIncapable(): void
    {
        $role = $this->createRole('Lockout Test Role B');
        $this->grantPermission($role->id, 'role.delete', '0');

        $this->assertFalse(AclLockoutGuard::isAdminCapableRole($role->id));
    }

    /**
     * Denying user.delete (a lockout-covered user write action) removes
     * capability.
     */
    public function testExplicitDenyOnUserDeleteMakesRoleIncapable(): void
    {
        $role = $this->createRole('Lockout Test Role UserWrite');
        $this->grantPermission($role->id, 'user.delete', '0');

        $this->assertFalse(AclLockoutGuard::isAdminCapableRole($role->id));
    }

    /**
     * user.get is out of lockout-covered scope: denying it does not remove
     * capability.
     */
    public function testDenyUserGetDoesNotAffectCapability(): void
    {
        $role = $this->createRole('Lockout Test Role UserGet');
        $this->grantPermission($role->id, 'user.get', '0');

        $this->assertTrue(AclLockoutGuard::isAdminCapableRole($role->id));
    }

    /**
     * PermissionController rejects demoting user.delete on the sole capable
     * role; user.get demotions are ignored by the lockout guard.
     */
    public function testPermissionControllerRejectsDemotingUserDeleteOnSoleCapableRole(): void
    {
        $role = $this->createRole('Lockout Test Role UserCtrl');
        $this->addMembership($role->id);

        $method = $this->getLockoutGuardMethod();

        $this->expectException(\Gaia\Exception\Permission::class);
        $method->invoke(new PermissionController(), 'user.delete', ['roleId' => $role->id, 'allowed' => '0']);
    }

    /**
     * PermissionController ignores user.get denials (resource not in
     * getLockoutResources()).
     */
    public function testPermissionControllerAllowsDemotingUserGet(): void
    {
        $role = $this->createRole('Lockout Test Role UserGetCtrl');
        $this->addMembership($role->id);

        $method = $this->getLockoutGuardMethod();

        // Should not throw — user.get is outside the lockout resource set.
        $method->invoke(new PermissionController(), 'user.get', ['roleId' => $role->id, 'allowed' => '0']);
        $this->assertTrue(true);
    }

    /**
     * Denying a module outside the lockout-covered scope (e.g. issue.delete)
     * never affects capability.
     */
    public function testDenyOutsideScopeDoesNotAffectCapability(): void
    {
        $role = $this->createRole('Lockout Test Role C');
        $this->grantPermission($role->id, 'issue.delete', '0');

        $this->assertTrue(AclLockoutGuard::isAdminCapableRole($role->id));
    }

    /**
     * A pending permission write can be simulated via $permissionOverrides
     * before it is persisted.
     */
    public function testPermissionOverrideSimulatesPendingDenial(): void
    {
        $role = $this->createRole('Lockout Test Role D');

        $this->assertTrue(AclLockoutGuard::isAdminCapableRole($role->id));
        $this->assertFalse(
            AclLockoutGuard::isAdminCapableRole($role->id, ['permission.delete' => '0'])
        );
    }

    /**
     * Overrides for resources outside the lockout-covered scope are ignored.
     */
    public function testPermissionOverrideOutsideScopeIsIgnored(): void
    {
        $role = $this->createRole('Lockout Test Role E');

        $this->assertTrue(
            AclLockoutGuard::isAdminCapableRole($role->id, ['issue.delete' => '0'])
        );
    }

    /**
     * The system retains access when one capable role has one member.
     */
    public function testSystemRetainsAccessWithOneCapableRoleAndMember(): void
    {
        $role = $this->createRole('Lockout Test Role F');
        $this->addMembership($role->id);

        $this->assertTrue(AclLockoutGuard::systemRetainsAdminPath());
    }

    /**
     * A capable role with zero members does not keep the system administrable.
     */
    public function testCapableRoleWithoutMembersDoesNotCount(): void
    {
        $this->createRole('Lockout Test Role G');

        $this->assertFalse(AclLockoutGuard::systemRetainsAdminPath());
    }

    /**
     * Simulating the deletion of the sole capable+member role (excludeRoleId)
     * leaves the system without administrable access.
     */
    public function testSystemRetainsAccessFalseWhenExcludingSoleCapableRole(): void
    {
        $role = $this->createRole('Lockout Test Role H');
        $this->addMembership($role->id);

        $this->assertFalse(
            AclLockoutGuard::systemRetainsAdminPath(['excludeRoleId' => $role->id])
        );
    }

    /**
     * A second capable role with a member keeps the system administrable
     * even when the first is excluded (role delete scenario).
     */
    public function testSystemRetainsAccessTrueWithSecondCapableRole(): void
    {
        $roleOne = $this->createRole('Lockout Test Role I');
        $this->addMembership($roleOne->id);

        $roleTwo = $this->createRole('Lockout Test Role J');
        $this->addMembership($roleTwo->id);

        $this->assertTrue(
            AclLockoutGuard::systemRetainsAdminPath(['excludeRoleId' => $roleOne->id])
        );
    }

    /**
     * Removing the last membership of the sole capable role (userrole delete
     * scenario) leaves the system without administrable access.
     */
    public function testSystemRetainsAccessFalseWhenRemovingLastMembership(): void
    {
        $role = $this->createRole('Lockout Test Role K');
        $membership = $this->addMembership($role->id);

        $this->assertFalse(
            AclLockoutGuard::systemRetainsAdminPath([
                'excludeUserroleId' => $membership->id,
                'affectedRoleIdForMembership' => $role->id,
            ])
        );
    }

    /**
     * A pending permission demotion that would deny the sole capable role's
     * last lockout-covered resource leaves the system without administrable
     * access.
     */
    public function testSystemRetainsAccessFalseWhenDemotingSoleCapableRole(): void
    {
        $role = $this->createRole('Lockout Test Role L');
        $this->addMembership($role->id);

        $this->assertFalse(
            AclLockoutGuard::systemRetainsAdminPath([
                'permissionOverrides' => [$role->id => ['userrole.delete' => '0']],
            ])
        );
    }

    /**
     * RoleController::deleteAction rejects deleting the sole role able to
     * administer permission/role/userrole resources.
     */
    public function testRoleControllerRejectsDeletingSoleCapableRole(): void
    {
        $role = $this->createRole('Lockout Test Role M');
        $this->addMembership($role->id);

        $controller = $this->makeController(RoleController::class, Role::class, $role->id);

        $this->expectException(\Gaia\Exception\Permission::class);
        $controller->deleteAction();
    }

    /**
     * RoleController::deleteAction allows deleting a role when another
     * capable role with a member remains.
     */
    public function testRoleControllerAllowsDeletingWhenAnotherCapableRoleRemains(): void
    {
        $roleToDelete = $this->createRole('Lockout Test Role N');
        $this->addMembership($roleToDelete->id);

        $survivingRole = $this->createRole('Lockout Test Role O');
        $this->addMembership($survivingRole->id);

        $controller = $this->makeController(RoleController::class, Role::class, $roleToDelete->id);
        $response = $controller->deleteAction();

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * UserroleController::deleteAction rejects removing the last membership
     * of the last capable role.
     */
    public function testUserroleControllerRejectsRemovingLastMembership(): void
    {
        $role = $this->createRole('Lockout Test Role P');
        $membership = $this->addMembership($role->id);

        $controller = $this->makeController(UserroleController::class, Userrole::class, $membership->id);

        $this->expectException(\Gaia\Exception\Permission::class);
        $controller->deleteAction();
    }

    /**
     * UserroleController::deleteAction allows removing a membership when
     * another capable role with a member remains.
     */
    public function testUserroleControllerAllowsRemovalWhenAnotherCapableRoleRemains(): void
    {
        $roleWithMembershipToRemove = $this->createRole('Lockout Test Role Q');
        $membership = $this->addMembership($roleWithMembershipToRemove->id);

        $survivingRole = $this->createRole('Lockout Test Role R');
        $this->addMembership($survivingRole->id);

        $controller = $this->makeController(UserroleController::class, Userrole::class, $membership->id);
        $response = $controller->deleteAction();

        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * PermissionController rejects a permission write that would deny the
     * sole capable role's last lockout-covered resource.
     */
    public function testPermissionControllerRejectsDemotingSoleCapableRole(): void
    {
        $role = $this->createRole('Lockout Test Role S');
        $this->addMembership($role->id);

        $method = $this->getLockoutGuardMethod();

        $this->expectException(\Gaia\Exception\Permission::class);
        $method->invoke(new PermissionController(), 'permission.delete', ['roleId' => $role->id, 'allowed' => '0']);
    }

    /**
     * PermissionController allows the same demotion when another capable
     * role with a member remains.
     */
    public function testPermissionControllerAllowsDemotingWhenAnotherCapableRoleRemains(): void
    {
        $roleToDemote = $this->createRole('Lockout Test Role T');
        $this->addMembership($roleToDemote->id);

        $survivingRole = $this->createRole('Lockout Test Role U');
        $this->addMembership($survivingRole->id);

        $method = $this->getLockoutGuardMethod();

        // Should not throw.
        $method->invoke(new PermissionController(), 'permission.delete', ['roleId' => $roleToDemote->id, 'allowed' => '0']);
        $this->assertTrue(true);
    }

    /**
     * Active (any casing) is usable; everything else is not.
     */
    public function testIsUsableAccountStatus(): void
    {
        $this->assertTrue(AclLockoutGuard::isUsableAccountStatus('Active'));
        $this->assertTrue(AclLockoutGuard::isUsableAccountStatus('active'));
        $this->assertTrue(AclLockoutGuard::isUsableAccountStatus('ACTIVE'));
        $this->assertFalse(AclLockoutGuard::isUsableAccountStatus('Inactive'));
        $this->assertFalse(AclLockoutGuard::isUsableAccountStatus('invited'));
        $this->assertFalse(AclLockoutGuard::isUsableAccountStatus(''));
        $this->assertFalse(AclLockoutGuard::isUsableAccountStatus(null));
    }

    /**
     * Non-Active memberships do not satisfy roleMemberCount / the invariant.
     */
    public function testInactiveMembershipDoesNotCount(): void
    {
        $role = $this->createRole('Lockout Test Role Inactive Mem');
        $inactiveUser = $this->createUser('inactive');
        $this->addMembership($role->id, $inactiveUser->id);

        $this->assertSame(0, AclLockoutGuard::roleMemberCount($role->id));
        $this->assertFalse(AclLockoutGuard::systemRetainsAdminPath());
    }

    /**
     * Simulating deactivation of the sole Active member leaves no admin path.
     */
    public function testSystemRetainsAccessFalseWhenExcludingSoleActiveMember(): void
    {
        global $currentUser;

        $role = $this->createRole('Lockout Test Role Exclude User');
        $this->addMembership($role->id);

        $this->assertFalse(
            AclLockoutGuard::systemRetainsAdminPath(['excludeUserId' => $currentUser->id])
        );
    }

    /**
     * Another Active member on a second capable role keeps the path when
     * excluding the first user.
     */
    public function testSystemRetainsAccessTrueWhenExcludingUserIfAnotherActiveRemains(): void
    {
        global $currentUser;

        $roleOne = $this->createRole('Lockout Test Role Exclude User A');
        $this->addMembership($roleOne->id);

        $otherUser = $this->createUser('active');
        $roleTwo = $this->createRole('Lockout Test Role Exclude User B');
        $this->addMembership($roleTwo->id, $otherUser->id);

        $this->assertTrue(
            AclLockoutGuard::systemRetainsAdminPath(['excludeUserId' => $currentUser->id])
        );
    }

    /**
     * UserController rejects deactivating the sole Active member of the sole
     * capable role.
     */
    public function testUserControllerRejectsDeactivatingSoleActiveAdmin(): void
    {
        global $currentUser;

        $role = $this->createRole('Lockout Test Role User Deact');
        $this->addMembership($role->id);

        $method = $this->getUserLockoutMethod();

        $this->expectException(\Gaia\Exception\Permission::class);
        $method->invoke(new UserController(), $currentUser->id);
    }

    /**
     * UserController allows deactivation when another Active admin remains.
     */
    public function testUserControllerAllowsDeactivatingWhenAnotherActiveAdminRemains(): void
    {
        global $currentUser;

        $roleOne = $this->createRole('Lockout Test Role User Deact A');
        $this->addMembership($roleOne->id);

        $otherUser = $this->createUser('active');
        $roleTwo = $this->createRole('Lockout Test Role User Deact B');
        $this->addMembership($roleTwo->id, $otherUser->id);

        $method = $this->getUserLockoutMethod();
        $method->invoke(new UserController(), $currentUser->id);
        $this->assertTrue(true);
    }

    /**
     * UserController::deleteAction rejects deleting the sole Active admin.
     */
    public function testUserControllerRejectsDeletingSoleActiveAdmin(): void
    {
        global $currentUser;

        $role = $this->createRole('Lockout Test Role User Del');
        $this->addMembership($role->id);

        $controller = $this->makeController(UserController::class, User::class, $currentUser->id);

        $this->expectException(\Gaia\Exception\Permission::class);
        $controller->deleteAction();
    }

    /**
     * @return \ReflectionMethod
     */
    private function getLockoutGuardMethod()
    {
        $reflection = new \ReflectionClass(PermissionController::class);
        $method = $reflection->getMethod('assertAclLockoutSafe');
        $method->setAccessible(true);
        return $method;
    }

    /**
     * @return \ReflectionMethod
     */
    private function getUserLockoutMethod()
    {
        $reflection = new \ReflectionClass(UserController::class);
        $method = $reflection->getMethod('assertUserRemainsAdministrable');
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Create a role with no permission rows (capable by default) and track
     * it for cleanup.
     *
     * @param  string $name
     * @return Role
     */
    private function createRole($name)
    {
        $role = new Role();
        $role->id = create_guid();
        $role->name = $name;
        $role->description = 'Created by AclLockoutGuardTest';
        $role->save();

        $this->createdModels[] = $role;
        return $role;
    }

    /**
     * Create (or overwrite) a permission row for a role and track it for cleanup.
     *
     * @param  string $roleId
     * @param  string $resourceName
     * @param  string $allowed
     * @return Permission
     */
    private function grantPermission($roleId, $resourceName, $allowed)
    {
        $permission = new Permission();
        $permission->id = create_guid();
        $permission->roleId = $roleId;
        $permission->resourceName = $resourceName;
        $permission->allowed = $allowed;
        $permission->save();

        $this->createdModels[] = $permission;
        return $permission;
    }

    /**
     * Add a membership for a user to a role and track it for cleanup.
     *
     * @param  string      $roleId
     * @param  string|null $userId Defaults to the shared Active test user.
     * @return Userrole
     */
    private function addMembership($roleId, $userId = null)
    {
        global $currentUser;

        $userrole = new Userrole();
        $userrole->id = create_guid();
        $userrole->roleId = $roleId;
        $userrole->userId = $userId ?: $currentUser->id;
        $userrole->save();

        $this->createdModels[] = $userrole;
        return $userrole;
    }

    /**
     * Create a disposable user and track it for cleanup.
     *
     * @param  string $accountStatus
     * @return User
     */
    private function createUser($accountStatus)
    {
        // users.id is varchar(36); use a GUID only (do not prefix).
        $userId = create_guid();
        $user = new User();
        $user->id = $userId;
        $user->email = 'acl-lockout-' . $userId . '@gaia.test';
        $user->name = 'ACL Lockout Extra User';
        $user->password = password_hash('unit-testing', PASSWORD_DEFAULT);
        $user->accountStatus = $accountStatus;
        $user->createdUserName = 'testUser';
        $user->modifiedUserName = 'testUser';
        $user->createdUser = 'test-user';
        $user->modifiedUser = 'test-user';
        $user->save();

        $this->createdModels[] = $user;
        return $user;
    }

    /**
     * Build a controller instance with the protected id/modelName/response
     * state that initialize() would normally set, without going through
     * OAuth/dispatch (mirrors the reflection approach used elsewhere in this
     * suite, e.g. TokenControllerTest).
     *
     * @param  string $controllerClass
     * @param  string $modelClass
     * @param  string $id
     * @return \Gaia\Core\MVC\REST\Controllers\RestController
     */
    private function makeController($controllerClass, $modelClass, $id)
    {
        $controller = new $controllerClass();
        $controller->setEventsManager(new \Phalcon\Events\Manager());

        $reflection = new \ReflectionClass($controller);

        $modelNameProperty = $reflection->getProperty('modelName');
        $modelNameProperty->setAccessible(true);
        $modelNameProperty->setValue($controller, $modelClass);

        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($controller, $id);

        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($controller, new \Phalcon\Http\Response());

        return $controller;
    }
}
