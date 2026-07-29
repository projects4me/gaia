<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

/**
 * Permission Model
 *
 * Persistence and query model for permission records. Owns loading and holding
 * the current user's effective action permissions for ACL evaluation.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Model
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Permission extends Model
{
    /**
     * Effective permissions for the loaded user, keyed by resourceName.
     *
     * @var array|null
     */
    protected static $effectivePermissions = null;

    /**
     * User id whose effective permissions are currently stored.
     *
     * @var string|null
     */
    protected static $loadedUserId = null;

    /**
     * Load and store effective action permissions for a user.
     *
     * Returns an array keyed by resourceName (e.g. issue.create), where each
     * value is a list of allow rows suitable for Acl evaluation:
     *
     * ```
     * [
     *   'issue.create' => [
     *     ['allowed' => 1, 'roleId' => '...'],
     *   ],
     * ]
     * ```
     *
     * Subsequent calls for the same user reuse the stored result.
     *
     * @param  string $userId
     * @return array
     */
    public static function loadEffectivePermissions($userId)
    {
        $userId = (string) $userId;
        if (self::$loadedUserId === $userId && self::$effectivePermissions !== null) {
            return self::$effectivePermissions;
        }

        $permission = new static();
        self::$effectivePermissions = $permission->fetchEffectivePermissions($userId);
        self::$loadedUserId = $userId;

        return self::$effectivePermissions;
    }

    /**
     * Return previously loaded effective permissions (empty until load).
     *
     * @return array
     */
    public static function getEffectivePermissions()
    {
        return self::$effectivePermissions !== null ? self::$effectivePermissions : [];
    }

    /**
     * Clear stored effective permissions.
     *
     * @return void
     */
    public static function clearEffectivePermissions()
    {
        self::$effectivePermissions = null;
        self::$loadedUserId = null;
    }

    /**
     * Query effective action permissions for a user from memberships + roles.
     *
     * @param  string $userId
     * @return array
     */
    protected function fetchEffectivePermissions($userId)
    {
        $permissionsByRole = $this->buildPermissionsQuery();
        $permissionsByRole->innerJoin(
            "Gaia\\MVC\\Models\\Membership",
            "Membership.roleId=Permission.roleId",
            "Membership"
        );
        $permissionsByRole->where(
            'Membership.userId=:userId:',
            ['userId' => $userId]
        );

        $permissions = [];
        foreach ($permissionsByRole->getQuery()->execute() as $value) {
            if (empty($value->resourceName)) {
                continue;
            }

            if (!isset($permissions[$value->resourceName])) {
                $permissions[$value->resourceName] = [];
            }
            $permissions[$value->resourceName][] = [
                'allowed' => $value->allowed,
                'roleId' => $value->roleId
            ];
        }

        return $permissions;
    }

    /**
     * Build the base permissions query for action-based ACL.
     *
     * @return \Phalcon\Mvc\Model\Query\Builder
     */
    private function buildPermissionsQuery()
    {
        $di = \Phalcon\Di::getDefault();

        $queryBuilder = $di->get('modelsManager')->createBuilder();
        $queryBuilder->columns([
            "Permission.resourceName",
            "Permission.allowed",
            'Permission.roleId as roleId',
        ]);
        $queryBuilder->from(['Permission' => 'Gaia\\MVC\\Models\\Permission']);

        return $queryBuilder;
    }
}
