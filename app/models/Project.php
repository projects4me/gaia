<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

/**
 * Project Model
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Project extends Model
{
    /**
     * Flag decides whether to execute hasManyToMany relationship queries
     * separately or not.
     * 
     * @var bool
     */
    public $splitQueries = false;

    /**
     * This function is used to apply ACL to the model.
     * 
     * @param \Phalcon\Mvc\Model $model
     * @param string $userId
     */
    public static function applyACLByModel($model, $userId)
    {
        $di = \Phalcon\Di::getDefault();
        $query = $model->getQuery();

        $metadata = $di->get('metaManager')->getModelMeta((new self)->modelAlias);
        $relatedKey = ($model instanceof \Gaia\MVC\Models\Project) ? 'id' : $metadata['acl']['group']['relatedKey'];

        $groups = self::getGroups($userId, $metadata['acl']['group']);
        $query->getPhalconQueryBuilder()->inWhere($model->modelAlias . ".$relatedKey", $groups);
    }

    /**
     * This function is used to get the list of the groups on which user have membership.
     * 
     * @param string $userId
     * @param array $aclMeta
     * @return array
     */
    private static function getGroups($userId, $aclMeta)
    {
        $groups = [];
        $di = \Phalcon\Di::getDefault();
        $queryBuilder = $di->get('modelsManager')->createBuilder();
        $queryBuilder->columns($aclMeta['columns']);
        $queryBuilder->from($aclMeta['relatedModel']);

        $queryBuilder->where($aclMeta['condition'], [
            'userId' => $userId
        ]);

        $memberships = $queryBuilder->getQuery()->execute();

        foreach ($memberships as $membership) {
            $groups[] = $membership['relatedId'];
        }

        return $groups;
    }

    /**
     * This function is used to apply acl on given relationship.
     * 
     * @param \Phalcon\Mvc\Model $model
     * @param string $relName
     * @param string $userId
     */
    public static function applyACLByRel($model, $relName, $userId)
    {
        $di = \Phalcon\Di::getDefault();

        $metadata = $di->get('metaManager')->getModelMeta((new self)->modelAlias);
        $groups = self::getGroups($userId, $metadata['acl']['group']);

        $relatedKey = self::getRelatedKey($model, $relName);

        //prepare IN
        $values = '';
        foreach ($groups as $group) {
            $values .= "'$group',";
        }

        $values = substr($values, 0, -1);
        $model->getRelationship()->addRelConditions($relName, "$relName.$relatedKey IN ($values)");
    }

    /**
     * This function returns related key of the related model.
     * 
     * @param \Phalcon\Mvc\Model $model
     * @param string $relName
     * @return string $relatedKey
     */
    public static function getRelatedKey($model, $relName)
    {
        $possibleRelatedKeys = ['projectId', 'relatedId'];

        $relationship = $model->getRelationship()->getRelationship($relName);
        $relatedModel = (new $relationship['relatedModel']());

        // If relatedModel is Project then return id as related key
        if ($relatedModel instanceof self) {
            return 'id';
        }

        $relatedModelMeta = $relatedModel->getModelsMetaData();
        $attributes = $relatedModelMeta->getAttributes($relatedModel);

        foreach ($possibleRelatedKeys as $relatedKey) {
            if (in_array($relatedKey, $attributes)) {
                return $relatedKey;
            }
        }
    }
}
