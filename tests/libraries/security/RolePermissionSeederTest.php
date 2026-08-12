<?php

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Security\Acl;
use Gaia\Libraries\Security\AclMapCatalog;
use Gaia\Libraries\Security\RolePermissionSeeder;
use Gaia\MVC\Models\Permission;
use Gaia\MVC\Models\Role;
use Gaia\MVC\Models\User;
use Gaia\MVC\Models\Userrole;
use Gaia\MVC\REST\Controllers\PermissionController;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Full-catalog materialization at role create (D-011) and related Acl behavior.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RolePermissionSeederTest extends TestCase
{
    /**
     * @var array<\Phalcon\Mvc\Model>
     */
    private $createdModels = [];

    /**
     * @var string[]
     */
    private $seededRoleIds = [];

    protected function setUp(): void
    {
        $this->ensureSharedLockoutUser();
        $this->ensureCurrentUserIsActive();
        $this->purgeSeededRoles();
        $this->createdModels = [];
        $this->seededRoleIds = [];
        Permission::clearEffectivePermissions();
    }

    protected function tearDown(): void
    {
        Permission::clearEffectivePermissions();

        foreach (array_reverse($this->createdModels) as $model) {
            $model->delete();
        }
        $this->createdModels = [];
        $this->purgeSeededRoles();

        // Restore default mode for other suites (constructor sets it).
        new Acl(\Phalcon\Di::getDefault(), Acl::RESOLUTION_PERMISSIVE);
    }

    /**
     * Keep the shared ACL lockout user usable for Role/Permission audit behaviors.
     */
    private function ensureCurrentUserIsActive(): void
    {
        global $currentUser;

        $this->ensureSharedLockoutUser();
        $currentUser = User::findFirstByEmail('acl-lockout-test@gaia.test');

        if (!$currentUser) {
            throw new \RuntimeException('Shared ACL lockout test user is missing after ensureSharedLockoutUser()');
        }
    }

    /**
     * Shared Active user also used by AclLockoutGuardTest.
     */
    private function ensureSharedLockoutUser(): void
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
                 WHERE id = ?",
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

    private function purgeSeededRoles(): void
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute(
            "DELETE FROM permissions WHERE \"roleId\" IN (
                SELECT id FROM roles WHERE name LIKE 'SeedTest%'
            )"
        );
        $db->execute(
            "DELETE FROM user_roles WHERE \"roleId\" IN (
                SELECT id FROM roles WHERE name LIKE 'SeedTest%'
            )"
        );
        $db->execute("DELETE FROM roles WHERE name LIKE 'SeedTest%'");
        foreach ($this->seededRoleIds as $roleId) {
            $db->execute('DELETE FROM permissions WHERE "roleId" = ?', [$roleId]);
            $db->execute('DELETE FROM user_roles WHERE "roleId" = ?', [$roleId]);
            $db->execute('DELETE FROM roles WHERE id = ?', [$roleId]);
        }
        $this->seededRoleIds = [];
    }

    private function track($model)
    {
        $this->createdModels[] = $model;
        return $model;
    }

    private function createRole($name)
    {
        $role = new Role();
        $role->id = create_guid();
        $role->name = $name;
        $role->description = 'RolePermissionSeeder unit test';
        $role->deleted = 0;
        $this->assertTrue($role->save(), 'Failed to save role fixture');
        $this->seededRoleIds[] = $role->id;
        return $this->track($role);
    }

    private function countRowsForRole($roleId)
    {
        return (int) Permission::count([
            'conditions' => 'roleId = :roleId:',
            'bind' => ['roleId' => $roleId],
        ]);
    }

    private function allowedFor($roleId, $resourceName)
    {
        $model = Permission::findFirst([
            'conditions' => 'roleId = :roleId: AND resourceName = :resourceName:',
            'bind' => [
                'roleId' => $roleId,
                'resourceName' => $resourceName,
            ],
        ]);
        $this->assertNotFalse($model, "Missing permission row {$resourceName} for role {$roleId}");
        return (int) $model->allowed;
    }

    private function isResourceAllowedViaAcl($userId, $resourceName, $mode)
    {
        Permission::clearEffectivePermissions();
        Permission::loadEffectivePermissions($userId);
        $acl = new Acl(\Phalcon\Di::getDefault(), $mode);
        $method = new \ReflectionMethod(Acl::class, 'isResourceAllowed');
        $method->setAccessible(true);
        return $method->invoke($acl, $resourceName);
    }

    public function testPermissiveSeedInsertsFullCatalogAllow(): void
    {
        $role = $this->createRole('SeedTest Permissive');
        $expected = RolePermissionSeeder::countCatalogResources();
        $this->assertGreaterThan(0, $expected);

        $inserted = RolePermissionSeeder::seedFullCatalog(
            $role->id,
            Acl::RESOLUTION_PERMISSIVE
        );

        $this->assertSame($expected, $inserted);
        $this->assertSame($expected, $this->countRowsForRole($role->id));
        $this->assertSame(1, $this->allowedFor($role->id, 'issue.get'));
        $this->assertSame(1, $this->allowedFor($role->id, 'issue.subject.get'));
        $this->assertSame(1, $this->allowedFor($role->id, 'issue.subject.create'));
        $this->assertSame(1, $this->allowedFor($role->id, 'issue.subject.update'));
    }

    public function testRestrictiveSeedInsertsFullCatalogDeny(): void
    {
        $role = $this->createRole('SeedTest Restrictive');
        $expected = RolePermissionSeeder::countCatalogResources();

        $inserted = RolePermissionSeeder::seedFullCatalog(
            $role->id,
            Acl::RESOLUTION_RESTRICTIVE
        );

        $this->assertSame($expected, $inserted);
        $this->assertSame(0, $this->allowedFor($role->id, 'issue.get'));
        $this->assertSame(0, $this->allowedFor($role->id, 'issue.subject.get'));
        $this->assertSame(0, $this->allowedFor($role->id, 'issue.subject.create'));
        $this->assertSame(0, $this->allowedFor($role->id, 'issue.subject.update'));
    }

    public function testRoleNameDoesNotOverrideRestrictiveSeed(): void
    {
        $role = $this->createRole('SeedTest Named Admin');
        RolePermissionSeeder::seedFullCatalog(
            $role->id,
            Acl::RESOLUTION_RESTRICTIVE
        );

        $this->assertSame(0, $this->allowedFor($role->id, 'permission.delete'));
        $this->assertSame(0, $this->allowedFor($role->id, 'issue.subject.get'));
    }

    public function testModeFlipDoesNotChangeSeededEffectiveAccess(): void
    {
        global $currentUser;

        $role = $this->createRole('SeedTest Flip');
        RolePermissionSeeder::seedFullCatalog($role->id, Acl::RESOLUTION_RESTRICTIVE);

        $membership = new Userrole();
        $membership->id = create_guid();
        $membership->userId = $currentUser->id;
        $membership->roleId = $role->id;
        $this->assertTrue($membership->save());
        $this->track($membership);

        // Under permissive aggregation, seeded deny rows still deny.
        $this->assertFalse(
            $this->isResourceAllowedViaAcl($currentUser->id, 'issue.get', Acl::RESOLUTION_PERMISSIVE)
        );
        // Under restrictive aggregation, same deny.
        $this->assertFalse(
            $this->isResourceAllowedViaAcl($currentUser->id, 'issue.get', Acl::RESOLUTION_RESTRICTIVE)
        );

        $this->assertSame(0, $this->allowedFor($role->id, 'issue.get'));
    }

    public function testMissingRowFollowsLiveResolutionMode(): void
    {
        global $currentUser;

        $role = $this->createRole('SeedTest Gaps');
        // Intentionally leave catalog unseeded — simulates catalog growth / legacy roles.

        $membership = new Userrole();
        $membership->id = create_guid();
        $membership->userId = $currentUser->id;
        $membership->roleId = $role->id;
        $this->assertTrue($membership->save());
        $this->track($membership);

        $this->assertTrue(
            $this->isResourceAllowedViaAcl($currentUser->id, 'issue.get', Acl::RESOLUTION_PERMISSIVE)
        );
        $this->assertFalse(
            $this->isResourceAllowedViaAcl($currentUser->id, 'issue.get', Acl::RESOLUTION_RESTRICTIVE)
        );
    }

    public function testTwoRolesSeededUnderDifferentModesDoNotLeak(): void
    {
        $permissiveRole = $this->createRole('SeedTest Leak P');
        $restrictiveRole = $this->createRole('SeedTest Leak R');

        RolePermissionSeeder::seedFullCatalog($permissiveRole->id, Acl::RESOLUTION_PERMISSIVE);
        RolePermissionSeeder::seedFullCatalog($restrictiveRole->id, Acl::RESOLUTION_RESTRICTIVE);

        $this->assertSame(1, $this->allowedFor($permissiveRole->id, 'issue.create'));
        $this->assertSame(0, $this->allowedFor($restrictiveRole->id, 'issue.create'));
        $this->assertNotSame(
            $this->allowedFor($permissiveRole->id, 'wiki.get'),
            $this->allowedFor($restrictiveRole->id, 'wiki.get')
        );
    }

    public function testFieldModeEmptyAllowedCoercesToNone(): void
    {
        $role = $this->createRole('SeedTest Field Unset');
        $controller = $this->makePermissionController();
        $method = new \ReflectionMethod(PermissionController::class, 'saveFieldModePermission');
        $method->setAccessible(true);

        $method->invoke($controller, [
            'resourceName' => 'issue.subject',
            'roleId' => $role->id,
            'allowed' => 'write',
        ]);
        $this->assertSame(1, $this->allowedFor($role->id, 'issue.subject.get'));

        $response = $method->invoke($controller, [
            'resourceName' => 'issue.subject',
            'roleId' => $role->id,
            'allowed' => '',
        ]);
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(0, $this->allowedFor($role->id, 'issue.subject.get'));
        $this->assertSame(0, $this->allowedFor($role->id, 'issue.subject.create'));
        $this->assertSame(0, $this->allowedFor($role->id, 'issue.subject.update'));

        $content = json_decode($response->getContent(), true);
        $this->assertSame('none', $content['data']['attributes']['allowed']);
    }

    public function testBinaryDeleteReplacesWithExplicitDeny(): void
    {
        $role = $this->createRole('SeedTest Delete Deny');
        $permission = new Permission();
        $permission->id = create_guid();
        $permission->roleId = $role->id;
        $permission->resourceName = 'tag.get';
        $permission->allowed = 1;
        $this->assertTrue($permission->save());
        $this->track($permission);

        $controller = $this->makePermissionController($permission->id);
        $response = $controller->deleteAction();
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(0, $this->allowedFor($role->id, 'tag.get'));
        $stillThere = Permission::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $permission->id],
        ]);
        $this->assertNotFalse($stillThere);
        $this->assertSame(0, (int) $stillThere->allowed);
    }

    public function testCatalogRowBuilderIncludesModuleAndFieldTriples(): void
    {
        $rows = RolePermissionSeeder::buildCatalogRows('probe', 0, [
            'get' => '0',
            'create' => '0',
            'update' => '0',
        ]);
        $names = array_column($rows, 'resourceName');
        $this->assertContains('issue.get', $names);
        $this->assertContains('permission.create', $names);
        $this->assertContains('issue.subject.get', $names);
        $this->assertContains('issue.subject.create', $names);
        $this->assertContains('issue.subject.update', $names);
        $this->assertNotContains('issue.subject', $names);
        $this->assertTrue(AclMapCatalog::isFieldActionResource('issue.subject.get'));
    }

    public function testResolveConfiguredResolutionModeDefaultsPermissive(): void
    {
        $mode = Acl::resolveConfiguredResolutionMode();
        $this->assertSame(Acl::RESOLUTION_PERMISSIVE, $mode);
    }

    /**
     * Minimal PermissionController for exercising private write/delete paths.
     *
     * @param  string|null $id
     * @return PermissionController
     */
    private function makePermissionController($id = null)
    {
        $controller = new PermissionController();
        $controller->setDI(\Phalcon\Di::getDefault());
        $controller->setEventsManager(new \Phalcon\Events\Manager());

        $reflection = new \ReflectionClass($controller);

        $modelNameProperty = $reflection->getProperty('modelName');
        $modelNameProperty->setAccessible(true);
        $modelNameProperty->setValue($controller, Permission::class);

        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($controller, new \Phalcon\Http\Response());

        if ($id !== null) {
            $idProperty = $reflection->getProperty('id');
            $idProperty->setAccessible(true);
            $idProperty->setValue($controller, $id);
        }

        return $controller;
    }
}
