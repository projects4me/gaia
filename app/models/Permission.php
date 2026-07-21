<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

/**
 * Permission Model
 *
 * Persistence and query model for permission records.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Model
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Permission extends Model
{
    /**
     * Load the effective permission rows for a user and action, optionally
     * scoped to a project membership role.
     *
     * Returns an array keyed by resource entity, where each value is a list of
     * permission flag rows suitable for Acl evaluation:
     *
     * ```
     * [
     *   'Issue' => [
     *     ['readF' => 0, 'projectId' => '...'],
     *   ],
     * ]
     * ```
     *
     * @param  string      $userId
     * @param  string      $action
     * @param  string|null $projectId
     * @return array
     */
    public function findEffectivePermissions($userId, $action, $projectId = null)
    {
        $permissionsByRole = $this->buildPermissionsQuery(null, $action);
        $permissionsByRole->innerJoin(
            "Gaia\\MVC\\Models\\Membership",
            "Membership.roleId=Permission.roleId",
            "Membership"
        );
        $permissionsByRole->where(
            'Membership.userId=:userId:',
            ['userId' => $userId]
        );

        if ($projectId) {
            $permissionsByRole->andWhere(
                'Membership.relatedId=:projectId:',
                ['projectId' => $projectId]
            );
        }

        $permissions = [];
        foreach ($permissionsByRole->getQuery()->execute() as $value) {
            if (!isset($permissions[$value->entity])) {
                $permissions[$value->entity] = [];
            }
            $permissions[$value->entity][] = [
                $action => $value->$action,
                'projectId' => $value->projectId,
                'roleId' => $value->roleId
            ];
        }

        return $permissions;
    }

    /**
     * Build the base permissions query for a given action.
     *
     * @param  string|null $resource
     * @param  string|null $action
     * @return \Phalcon\Mvc\Model\Query\Builder
     */
    private function buildPermissionsQuery($resource = null, $action = null)
    {
        $di = \Phalcon\Di::getDefault();

        $queryBuilder = $di->get('modelsManager')->createBuilder();
        $queryBuilder->columns([
            "Permission.$action",
            'Permission.roleId as roleId',
            'Resource1.entity',
            'Membership.relatedId as projectId'
        ]);
        $queryBuilder->from(['Resource1' => 'Gaia\\MVC\\Models\\Resource']);
        $queryBuilder->leftJoin("Gaia\\MVC\\Models\\Permission", "Permission.resourceId=Resource1.id", "Permission");

        if ($resource) {
            $queryBuilder->where('Resource1.entity=:resource:', ["resource" => $resource]);
        }

        return $queryBuilder;
    }
}
