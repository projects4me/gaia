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
     * Field access modes.
     *
     * @var string
     * @readonly
     */
    public const FIELD_ACCESS_NONE = 'none';

    /**
     * Field access mode: read.
     *
     * @var string
     * @readonly
     */
    public const FIELD_ACCESS_READ = 'read';

    /**
     * Field access mode: write.
     *
     * @var string
     * @readonly
     */
    public const FIELD_ACCESS_WRITE = 'write';

    /**
     * Allowed values for public field-mode permission writes (excluding unset '').
     *
     * @var array
     */
    public const FIELD_ACCESS_MODES = [
        self::FIELD_ACCESS_NONE,
        self::FIELD_ACCESS_READ,
        self::FIELD_ACCESS_WRITE,
    ];

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
     * Return permission rows for a single resource name (empty if none).
     *
     * @param  string $resourceName
     * @return array
     */
    public static function getPermissionsForResource($resourceName)
    {
        $permissions = self::getEffectivePermissions();
        return isset($permissions[$resourceName]) ? $permissions[$resourceName] : [];
    }

    /**
     * Whether any permission rows exist for the given resource name.
     *
     * @param  string $resourceName
     * @return bool
     */
    public static function hasResource($resourceName)
    {
        $permissions = self::getEffectivePermissions();
        return isset($permissions[$resourceName]);
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
     * Expand a field access mode into get/create/update allowed flags.
     *
     * - none  → get=0, create=0, update=0
     * - read  → get=1, create=0, update=0  (Read Only)
     * - write → get=1, create=1, update=1  (Write + Read)
     *
     * @param  string $mode none|read|write
     * @return array{get: string, create: string, update: string}
     * @throws \InvalidArgumentException
     */
    public static function expandFieldAccessMode($mode)
    {
        switch ($mode) {
            case self::FIELD_ACCESS_NONE:
                return ['get' => '0', 'create' => '0', 'update' => '0'];
            case self::FIELD_ACCESS_READ:
                return ['get' => '1', 'create' => '0', 'update' => '0'];
            case self::FIELD_ACCESS_WRITE:
                return ['get' => '1', 'create' => '1', 'update' => '1'];
            default:
                throw new \InvalidArgumentException(
                    "Unsupported field access mode: {$mode}"
                );
        }
    }

    /**
     * This function is used to derive the field access mode from stored get/create/update allowed flags.
     *
     * @param  array $flags Keys get|create|update with allowed values
     * @return string none|read|write
     */
    public static function deriveFieldAccessMode(array $flags)
    {
        $get = self::normalizeAllowedFlag(isset($flags['get']) ? $flags['get'] : null);
        $create = self::normalizeAllowedFlag(isset($flags['create']) ? $flags['create'] : null);
        $update = self::normalizeAllowedFlag(isset($flags['update']) ? $flags['update'] : null);

        if ($create === 1 || $update === 1) {
            return self::FIELD_ACCESS_WRITE;
        }

        if ($get === 1) {
            return self::FIELD_ACCESS_READ;
        }

        if ($get === 0 && $create === 0 && $update === 0) {
            return self::FIELD_ACCESS_NONE;
        }

        // Unset / empty → permissive full access (Write + Read).
        return self::FIELD_ACCESS_WRITE;
    }

    /**
     * Normalize an allowed flag: 1, 0, or null when unset/empty.
     *
     * @param  mixed $flagValue
     * @return int|null
     */
    public static function normalizeAllowedFlag($flagValue)
    {
        if ($flagValue === null || $flagValue === '') {
            return null;
        }

        return ((int) $flagValue > 0) ? 1 : 0;
    }

    /**
     * Query effective action permissions for a user from userroles + permissions.
     *
     * @param  string $userId
     * @return array
     */
    protected function fetchEffectivePermissions($userId)
    {
        $permissionsByRole = $this->buildPermissionsQuery();
        $permissionsByRole->innerJoin(
            "Gaia\\MVC\\Models\\Userrole",
            "Userrole.roleId=Permission.roleId",
            "Userrole"
        );
        $permissionsByRole->where(
            'Userrole.userId=:userId:',
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
