<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\MVC\Models\Permission;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Materializes a full ACL catalog of permission rows for a role at create time.
 *
 * Seed values come only from the resolution mode in effect when the role is born.
 * Privilege is not inferred from role name — capability lives in stored grants
 * (D-052). After seeding, those rows are durable; missing rows (e.g. later
 * catalog growth) still follow live mode.
 *
 * @author  Rana Nouman <ranamnouman@gmail.com>
 * @package Core\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RolePermissionSeeder
{
    /**
     * Batch size for multi-row INSERTs.
     *
     * @var int
     */
    private const INSERT_BATCH_SIZE = 200;

    /**
     * Seed every module-action and field-action resource for a role.
     *
     * @param  string      $roleId
     * @param  string|null $resolutionMode Null reads system.acl.resolutionMode.
     * @return int Number of rows inserted
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public static function seedFullCatalog($roleId, $resolutionMode = null)
    {
        $roleId = (string) $roleId;
        if ($roleId === '') {
            throw new \InvalidArgumentException('roleId is required to seed permissions');
        }

        $resolutionMode = Acl::normalizeResolutionMode(
            $resolutionMode === null
                ? Acl::resolveConfiguredResolutionMode()
                : $resolutionMode
        );

        $allowAll = $resolutionMode === Acl::RESOLUTION_PERMISSIVE;
        $moduleAllowed = $allowAll ? 1 : 0;
        $fieldMode = $allowAll
            ? Permission::FIELD_ACCESS_WRITE
            : Permission::FIELD_ACCESS_NONE;
        $fieldFlags = Permission::expandFieldAccessMode($fieldMode);

        $rows = self::buildCatalogRows($roleId, $moduleAllowed, $fieldFlags);
        if (empty($rows)) {
            return 0;
        }

        return self::insertRows($rows);
    }

    /**
     * Build permission row payloads for the current ACL catalogs.
     *
     * @param  string $roleId
     * @param  int    $moduleAllowed
     * @param  array  $fieldFlags get|create|update => '0'|'1'
     * @return array<int, array{id:string,roleId:string,resourceName:string,allowed:int}>
     */
    public static function buildCatalogRows($roleId, $moduleAllowed, array $fieldFlags)
    {
        global $settings;

        $moduleActions = [];
        $moduleFields = [];
        if (isset($settings['system']['acl']['moduleActions'])) {
            $moduleActions = $settings['system']['acl']['moduleActions']->toArray();
        }
        if (isset($settings['system']['acl']['moduleFields'])) {
            $moduleFields = $settings['system']['acl']['moduleFields']->toArray();
        }

        $rows = [];
        $seen = [];

        foreach ($moduleActions as $moduleDefinition) {
            if (empty($moduleDefinition['actions']) || !is_array($moduleDefinition['actions'])) {
                continue;
            }
            foreach ($moduleDefinition['actions'] as $actionDefinition) {
                if (empty($actionDefinition['resourceName'])) {
                    continue;
                }
                $resourceName = $actionDefinition['resourceName'];
                if (isset($seen[$resourceName])) {
                    continue;
                }
                $seen[$resourceName] = true;
                $rows[] = self::makeRow($roleId, $resourceName, $moduleAllowed);
            }
        }

        foreach ($moduleFields as $moduleDefinition) {
            if (empty($moduleDefinition['fields']) || !is_array($moduleDefinition['fields'])) {
                continue;
            }
            $moduleName = isset($moduleDefinition['module'])
                ? $moduleDefinition['module']
                : '';
            foreach ($moduleDefinition['fields'] as $fieldDefinition) {
                if (empty($fieldDefinition['resourceName']) || empty($fieldDefinition['field'])) {
                    continue;
                }
                $field = $fieldDefinition['field'];
                foreach (AclMapCatalog::FIELD_ACTIONS as $action) {
                    $resourceName = AclMapCatalog::buildFieldActionResourceName(
                        $moduleName,
                        $field,
                        $action
                    );
                    if (isset($seen[$resourceName])) {
                        continue;
                    }
                    $seen[$resourceName] = true;
                    $allowed = isset($fieldFlags[$action]) ? (int) $fieldFlags[$action] : 0;
                    $rows[] = self::makeRow($roleId, $resourceName, $allowed);
                }
            }
        }

        return $rows;
    }

    /**
     * Count expected catalog resource names (module actions + field triples).
     *
     * @return int
     */
    public static function countCatalogResources()
    {
        $rows = self::buildCatalogRows('count-probe', 0, [
            'get' => '0',
            'create' => '0',
            'update' => '0',
        ]);
        return count($rows);
    }

    /**
     * @param  string $roleId
     * @param  string $resourceName
     * @param  int    $allowed
     * @return array{id:string,roleId:string,resourceName:string,allowed:int}
     */
    private static function makeRow($roleId, $resourceName, $allowed)
    {
        return [
            'id' => create_guid(),
            'roleId' => $roleId,
            'resourceName' => $resourceName,
            'allowed' => (int) $allowed,
        ];
    }

    /**
     * Bulk-insert permission rows using the shared DB connection (honors open tx).
     *
     * @param  array $rows
     * @return int
     * @throws \RuntimeException
     */
    private static function insertRows(array $rows)
    {
        $di = \Phalcon\Di::getDefault();
        if (!$di || !$di->has('db')) {
            throw new \RuntimeException('Database service is not available for permission seeding');
        }

        $db = $di->get('db');
        $now = date('Y-m-d H:i:s');
        $inserted = 0;

        foreach (array_chunk($rows, self::INSERT_BATCH_SIZE) as $batch) {
            $placeholders = [];
            $bind = [];
            foreach ($batch as $index => $row) {
                $placeholders[] = "(?,?,?,?,?,?)";
                $bind[] = $row['id'];
                $bind[] = $row['roleId'];
                $bind[] = $row['resourceName'];
                $bind[] = $row['allowed'];
                $bind[] = $now;
                $bind[] = $now;
            }

            $sql = 'INSERT INTO permissions '
                . '(id, "roleId", "resourceName", allowed, "dateCreated", "dateModified") '
                . 'VALUES ' . implode(', ', $placeholders);

            if (!$db->execute($sql, $bind)) {
                throw new \RuntimeException('Failed to seed role permissions');
            }
            $inserted += count($batch);
        }

        return $inserted;
    }
}
