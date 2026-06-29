<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Gaia\Libraries\Utils\Util;

/**
 * User Model
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class User extends Model
{
    /**
     * Flag decides whether to execute hasManyToMany relationship queries
     * separately or not.
     *
     * @var boolean
     */
    public $splitQueries = false;

    /**
     * Use unbuffered PDO execution to avoid loading the full result set into
     * memory.
     *
     * @var bool
     */
    protected $unbufferedExecution = true;

    /**
     * This function is used to apply ACL to the model.
     *
     * @param \Phalcon\Mvc\Model $model
     * @param string $userId
     */
    public static function applyACLByModel($model, $userId, $accessLevel = null, $projectId = null)
    {
        $di = \Phalcon\Di::getDefault();
        $query = $model->getQuery();

        $aclMeta = ($di->get('metaManager')->getModelMeta($model->modelAlias))['acl'];

        if (isset($aclMeta['assignment']['field'])) {
            $query->getPhalconQueryBuilder()->andWhere($aclMeta['assignment']['condition'], [
                'userId' => $userId
            ]);
        } else {
            $relatedModel = $aclMeta['assignment']['relatedModel'];
            $query->getPhalconQueryBuilder()->innerJoin($relatedModel['namespace'], $relatedModel['condition'], $relatedModel['alias']);
            $query->getPhalconQueryBuilder()->setBindParams(
                ["userId" => $userId],
                true
            );
        }
    }

    // /**
    //  * Normalizes project IDs passed as an array or a quoted SQL IN-list string.
    //  *
    //  * @param array|string $projectIds
    //  * @return array
    //  */
    // private static function normalizeProjectIds($projectIds)
    // {
    //     if (is_array($projectIds)) {
    //         return $projectIds;
    //     }

    //     return array_values(array_filter(array_map(
    //         'trim',
    //         explode(',', str_replace("'", '', $projectIds))
    //     )));
    // }

    /**
     * This function is used to apply acl on given relationship.
     *
     * @param \Phalcon\Mvc\Model $model
     * @param string $relName
     * @param string $userId
     */
    public static function applyACLByRel($model, $relName, $userId, $projectId = null, $accessLevel = null)
    {
        $di = \Phalcon\Di::getDefault();
        $relatedModelName = $di->get('metaManager')->getRelatedModelName($model->modelAlias, $relName);
        $aclMeta = ($di->get('metaManager')->getModelMeta($relatedModelName))['acl'];
        $relatedKey = \Gaia\MVC\Models\Project::getRelatedKey($model, $relName);
        
        if (isset($aclMeta['assignment']['field'])) {
            $condition = "$relName.createdUser = '$userId'";
            if ($projectId && $relatedKey) {
                $condition .= "AND $relName.$relatedKey = '$projectId'";
            }
            $model->getRelationship()->addRelConditions($relName, $condition);
        } else {
            $relatedModel = $aclMeta['assignment']['relatedModel'];
            if($relatedModel) {
            $modelAlias = $model->modelAlias;
            if($modelAlias === "User") {
                $model->getQuery()->getPhalconQueryBuilder()->setBindParams(
                    ["userId" => $userId],
                    true
                );
                return;
            }
            $condition = preg_replace('/Project.id/', $modelAlias."."."projectId", $relatedModel['condition']);
            $model->getQuery()->getPhalconQueryBuilder()->innerJoin(
                $relatedModel['namespace'],
                $condition,
                $relatedModel['alias']
            );
            $model->getQuery()->getPhalconQueryBuilder()->setBindParams(
                ["userId" => $userId],
                true
            );
    }
        }
    }

    /**
     * Retrieves the first system user with Admin role from the database.
     *
     * @return \Gaia\MVC\Models\User|null Returns the first admin user found or null if none exists
     */
    public static function getSystemUser()
    {
        $di = \Phalcon\Di::getDefault();
        $builder = $di->get('modelsManager')->createBuilder();
        $builder->from(['User' => 'Gaia\MVC\Models\User'])
            ->columns(['User.id','User.name'])
            ->leftJoin('Gaia\MVC\Models\Membership', 'm.userId = User.id', 'm')
            ->leftJoin('Gaia\MVC\Models\Role', 'r.id = m.roleId', 'r')
            ->where('r.name = :roleName:', ['roleName' => 'Admin'])
            ->limit(1);

        $result = $builder->getQuery()->execute()->getFirst();

        return User::findFirstById($result->id);
    }

    /**
     * Sets the current user in the global scope.
     *
     * @param \Gaia\MVC\Models\User $user The user instance to set as current user
     * @global \Gaia\MVC\Models\User $currentUser Global variable holding current user instance
     */
    public static function setCurrentUser(\Gaia\MVC\Models\User $user)
    {
        global $currentUser;
        $currentUser = $user;
    }
}
