<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\MVC\Models\Role;
use Gaia\MVC\Models\User;
use Gaia\MVC\Models\Userrole;

/**
 * Guards against removing every path to administer ACL-critical modules.
 *
 * A role is "admin-capable" when it has no explicit deny for any of the
 * lockout-covered module actions required to administer permissions, roles,
 * role assignments, and users (`permission.*`, `role.*`, `userrole.*`, and
 * `user.{create,update,delete}` — `user.get` is intentionally out of scope).
 * This mirrors `Acl::isResourceAllowed()` under the default permissive
 * resolution mode: a missing permission row allows access, so only an
 * explicit deny (`0` or `''`) removes capability.
 *
 * The system retains an admin path as long as at least one non-deleted role
 * is admin-capable AND has at least one *usable* membership (non-deleted
 * `user_roles` row whose user is non-deleted and has `accountStatus` Active,
 * case-insensitive). This invariant is checked before role deletion, before
 * a permission write would deny a lockout-covered action, before removing
 * the last usable member of a role, and before deactivating or deleting a
 * user who is that last usable member.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia\Libraries\Security
 */
class AclLockoutGuard
{
    /**
     * Modules that must remain administrable by at least one role.
     *
     * @var array
     */
    public const LOCKOUT_MODULES = ['permission', 'role', 'userrole', 'user'];

    /**
     * Default module actions covered by the lockout invariant.
     *
     * @var array
     */
    public const LOCKOUT_ACTIONS = ['get', 'create', 'update', 'delete'];

    /**
     * Per-module action overrides (falls back to {@see LOCKOUT_ACTIONS}).
     *
     * `user.get` is excluded: read access alone is not required to keep the
     * ACL administration path alive.
     *
     * @var array
     */
    public const LOCKOUT_MODULE_ACTIONS = [
        'user' => ['create', 'update', 'delete'],
    ];

    /**
     * Canonical usable account status (compared case-insensitively).
     *
     * @var string
     */
    public const USABLE_ACCOUNT_STATUS = 'Active';

    /**
     * Build the full list of `{module}.{action}` resource names in scope for
     * the lockout invariant.
     *
     * @return array
     */
    public static function getLockoutResources()
    {
        $resources = [];
        foreach (self::LOCKOUT_MODULES as $module) {
            $actions = isset(self::LOCKOUT_MODULE_ACTIONS[$module])
                ? self::LOCKOUT_MODULE_ACTIONS[$module]
                : self::LOCKOUT_ACTIONS;
            foreach ($actions as $action) {
                $resources[] = $module . '.' . $action;
            }
        }
        return $resources;
    }

    /**
     * Whether an account status counts as a usable admin membership.
     *
     * @param  mixed $accountStatus
     * @return bool
     */
    public static function isUsableAccountStatus($accountStatus)
    {
        if ($accountStatus === null || $accountStatus === '') {
            return false;
        }

        return strcasecmp((string) $accountStatus, self::USABLE_ACCOUNT_STATUS) === 0;
    }

    /**
     * Normalize a stored `allowed` flag to binary allow (1) or deny (0).
     *
     * Mirrors `Acl::normalizeFlag()`: missing/empty means deny for an
     * existing row, while a missing row entirely means allow (handled by the
     * caller, not this method).
     *
     * @param  mixed $flagValue
     * @return int
     */
    public static function normalizeFlag($flagValue)
    {
        if ($flagValue === null || $flagValue === '') {
            return 0;
        }

        return ((int) $flagValue > 0) ? 1 : 0;
    }

    /**
     * Whether a role remains admin-capable, optionally simulating a pending
     * permission write before it is persisted.
     *
     * @param  string $roleId
     * @param  array  $permissionOverrides Map of resourceName => proposed allowed value.
     *                                      Only lockout-covered resource names are considered.
     * @return bool
     */
    public static function isAdminCapableRole($roleId, array $permissionOverrides = [])
    {
        $rowsByResource = self::findLockoutPermissionRows($roleId);

        foreach ($permissionOverrides as $resourceName => $overrideValue) {
            if (!in_array($resourceName, self::getLockoutResources(), true)) {
                continue;
            }
            // Pending write replaces all stored values for this resource.
            $rowsByResource[$resourceName] = [$overrideValue];
        }

        foreach (self::getLockoutResources() as $resourceName) {
            if (!array_key_exists($resourceName, $rowsByResource)) {
                // No row for this role/resource: permissive default allows it.
                continue;
            }

            // Mirror RESOLUTION_PERMISSIVE: any allow wins; deny only when
            // every row for the resource is an explicit deny (handles duplicate
            // permission rows for the same role/resource).
            $resourceAllowed = false;
            foreach ($rowsByResource[$resourceName] as $allowedValue) {
                if (self::normalizeFlag($allowedValue) === 1) {
                    $resourceAllowed = true;
                    break;
                }
            }

            if (!$resourceAllowed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Count usable `user_roles` memberships for a role.
     *
     * A membership counts only when both the assignment and the user are
     * non-deleted and the user's `accountStatus` is Active (case-insensitive).
     * Optionally exclude one membership row and/or one user (to simulate a
     * pending removal or deactivation/deletion).
     *
     * @param  string      $roleId
     * @param  string|null $excludeUserroleId
     * @param  string|null $excludeUserId
     * @return int
     */
    public static function roleMemberCount($roleId, $excludeUserroleId = null, $excludeUserId = null)
    {
        $memberships = Userrole::find([
            'conditions' => 'roleId = :roleId:',
            'bind' => ['roleId' => $roleId],
        ]);

        $count = 0;
        foreach ($memberships as $membership) {
            if ($excludeUserroleId && (string) $membership->id === (string) $excludeUserroleId) {
                continue;
            }

            if ($excludeUserId && (string) $membership->userId === (string) $excludeUserId) {
                continue;
            }

            $user = User::findFirstById($membership->userId);
            if (!$user) {
                continue;
            }

            if (self::isUsableAccountStatus($user->accountStatus)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Whether at least one role would still be admin-capable and hold at
     * least one usable membership after a hypothetical change.
     *
     * Supported simulation options:
     * - `excludeRoleId`: treat this role as deleted (e.g. role delete).
     * - `permissionOverrides`: `[roleId => [resourceName => proposedAllowed]]`.
     * - `excludeUserroleId` + `affectedRoleIdForMembership`: treat this
     *   membership as removed when counting members for that one role
     *   (e.g. userrole delete).
     * - `excludeUserId`: treat this user as non-usable for every role's
     *   membership count (e.g. user deactivate or delete).
     *
     * @param  array $options
     * @return bool
     */
    public static function systemRetainsAdminPath(array $options = [])
    {
        $excludeRoleId = isset($options['excludeRoleId']) ? $options['excludeRoleId'] : null;
        $permissionOverrides = isset($options['permissionOverrides']) ? $options['permissionOverrides'] : [];
        $excludeUserroleId = isset($options['excludeUserroleId']) ? $options['excludeUserroleId'] : null;
        $affectedRoleId = isset($options['affectedRoleIdForMembership']) ? $options['affectedRoleIdForMembership'] : null;
        $excludeUserId = isset($options['excludeUserId']) ? $options['excludeUserId'] : null;

        // Role::find() already excludes soft-deleted rows via softDeleteBehavior.
        $roles = Role::find();

        foreach ($roles as $role) {
            if ($excludeRoleId && (string) $role->id === (string) $excludeRoleId) {
                continue;
            }

            $overrides = isset($permissionOverrides[$role->id]) ? $permissionOverrides[$role->id] : [];
            if (!self::isAdminCapableRole($role->id, $overrides)) {
                continue;
            }

            $excludeMembershipId = ($affectedRoleId && (string) $affectedRoleId === (string) $role->id)
                ? $excludeUserroleId
                : null;

            if (self::roleMemberCount($role->id, $excludeMembershipId, $excludeUserId) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Query lockout-covered permission rows for a single role.
     *
     * @param  string $roleId
     * @return array Map of resourceName => list of allowed values (supports
     *               duplicate rows for the same role/resource)
     */
    private static function findLockoutPermissionRows($roleId)
    {
        $di = \Phalcon\Di::getDefault();
        $builder = $di->get('modelsManager')->createBuilder()
            ->columns([
                'Permission.resourceName as resourceName',
                'Permission.allowed as allowed',
            ])
            ->from(['Permission' => 'Gaia\\MVC\\Models\\Permission'])
            ->where('Permission.roleId = :roleId:', ['roleId' => $roleId])
            ->inWhere('Permission.resourceName', self::getLockoutResources());

        $rows = [];
        foreach ($builder->getQuery()->execute() as $row) {
            $rows[$row->resourceName][] = $row->allowed;
        }

        return $rows;
    }
}
