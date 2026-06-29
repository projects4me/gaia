<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;
use Gaia\Libraries\Utils\Util;

/**
 * Description of aclBehavior
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class aclBehavior extends Behavior implements BehaviorInterface
{
    /**
     * This contains the models according to the access level.
     *
     * @var array
     */
    protected $accessLevelMapping = [
        "1" => \Gaia\MVC\Models\User::class ,
        "2" => \Gaia\MVC\Models\Project::class
    ];

    /**
     * This function is called whenever an event is fired.
     *
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            $this->$eventType($model);
        }
    }

    /**
     * This function is called before a query is executed
     *
     * @param  ModelInterface $model
     * @return void
     */
    protected function beforeQuery($model)
    {
        //Add ACL on model
        $userId = $GLOBALS['currentUser']->id;
        $canApplyAcl = $this->canApplyModelAcl($model->modelAlias);
        if (!$canApplyAcl) {
            return;
        }

        $di = \Phalcon\Di::getDefault();
        $permission = $di->get('permission');
        $accessLevel = $permission->getAccess($model->modelAlias);
        $groups = $di->get('metaManager')->getModelGroups($model->modelAlias);

        foreach ($accessLevel as $accessData) {
            $resourceAccessLevel = $accessData['accessLevel'];
            if (array_key_exists($resourceAccessLevel, $this->accessLevelMapping)) {
                $this->accessLevelMapping[$resourceAccessLevel]::applyACLByModel($model, $userId, $resourceAccessLevel, $accessData['projectId']);
            } else if ($resourceAccessLevel === '0' && is_array($groups)) {
                \Gaia\MVC\Models\Project::applyACLByModel($model, $userId, '0', $accessData['projectId']);
            }
        }
    }

    /**
     * This function is called before a joins are added into the model.
     *
     * @param  ModelInterface $model
     * @return void
     */
    protected function beforeJoins($model)
    {
        $relationship = $model->getRelationship();
        $requestedRelationships = $relationship->getRequestedRelationships();
        $userId = $GLOBALS['currentUser']->id;

        $di = \Phalcon\Di::getDefault();
        $permissionModel = $di->get('permission');

        foreach ($requestedRelationships as $rel) {
            $relatedModelName = $di->get('metaManager')->getRelatedModelName($model->modelAlias, $rel);
            $canApplyAcl = $this->canApplyModelAcl($relatedModelName);
            if (!$canApplyAcl) {
                continue;
            }
            $permissions = $permissionModel->getAccess($relatedModelName);
            $relMeta = $relationship->getRelationship($rel);
            $groups = $di->get('metaManager')->getModelGroups($relatedModelName);
            if ($permissions) {
                foreach ($permissions as $permission) {
                    $accessLevel = $permission['accessLevel'];
                    $projectId = $permission['projectId'];
                    
                    if (array_key_exists($accessLevel, $this->accessLevelMapping)) {
                        $this->accessLevelMapping[$accessLevel]::applyACLByRel($model, $rel, $userId, $projectId, $accessLevel);
                    } elseif ($accessLevel === '0' && is_array($groups)) {
                        $isGroup = $this->relIsGroup($rel, $model->modelAlias);
                        if ($isGroup) {
                            $key = $this->getRelGroupKey($relMeta, $model, $rel);
                            $model->getRelationship()->addRelConditions($rel, "$key != '$projectId'");
                        } else if (is_array($groups)) {
                            \Gaia\MVC\Models\Project::applyACLByRel($model, $rel, $userId, $projectId, $accessLevel);
                        } else {
                            // If access level is 0 and not a group then append 0 in the join condition to not retrieve related model.
                            $model->getRelationship()->addRelConditions($rel, "0");
                        }
                    }
                }
            }

            // if (array_key_exists($accessLevel, $this->accessLevelMapping)) {
            //     // If the related model is group itself then apply access level 2.
            //     ($this->relIsGroup($rel, $model->modelAlias)) && ($accessLevel = 2);
            //     $this->accessLevelMapping[$accessLevel]::applyACLByRel($model, $rel, $userId);
            // } elseif ($accessLevel === '0') {
            //     // If access level is 0 then append 0 in the join condition to not retrieve related model.
            //     $model->getRelationship()->addRelConditions($rel, "0");
            // }
        }
    }

    /**
     * This function is used to check whether the related model of the given relationship is
     * a group or not.
     *
     * @method relIsGroup
     * @param $modelAlias The alias of model.
     * @param  $relName The name of the relationship.
     * @return boolean
     */
    private function relIsGroup($relName, $modelAlias)
    {
        $di = \Phalcon\Di::getDefault();

        $isGroup = false;
        $relatedModelName = $di->get('metaManager')->getRelatedModelName($modelAlias, $relName);
        $metadata = $di->get('metaManager')->getModelMeta($relatedModelName);

        if (isset($metadata['acl']['group'])) {
            $isGroup = true;
        }
        return $isGroup;
    }

    private function canApplyModelAcl($modelName)
    {
        $canApplyAcl = true;
        $modelNamespace = "\\Gaia\\MVC\\Models\\$modelName";
        if (class_exists($modelNamespace)) {
            $model = new $modelNamespace();
            $canApplyAcl = $model->isAclAllowed();
        }
        return $canApplyAcl;
    }

    private function getRelGroupKey($relMeta, $model, $relName)
    {
        $relType = $model->getRelationship()->getRelationshipType($relName);
        if ($relType === 'hasManyToMany') {
            $alias = Util::extractClassFromNamespace($relMeta['relatedModel']);
            $key = $relName . $alias . "." . $relMeta['lhsKey'];
            return $key;
        }
        return $relName."."."id";
    }
}
