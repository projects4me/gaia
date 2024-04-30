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
     * This function is used to apply ACL to the model.
     *
     * @param \Phalcon\Mvc\Model $model
     * @param string $userId
     */
    public static function applyACLByModel($model, $userId)
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
                [
                "userId" => $userId,
            ],
                true
            );
        }
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
        if ($model->getRelationship()->getRelationshipType($relName)) {
            $relMetadata = $di->get('metaManager')->getRelationshipMeta($model->modelAlias, $relName);
            $relatedModelName = Util::extractClassFromNamespace($relMetadata['relatedModel']);

            // Concatenate relatedModel alias with relName e.g. membersMembership
            $relName .= $relatedModelName;
        }

        $model->getRelationship()->addRelConditions($relName, "$relName.createdUser = '$userId'");
    }
}
