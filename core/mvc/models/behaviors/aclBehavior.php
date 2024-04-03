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

        $di = \Phalcon\Di::getDefault();
        $permission = $di->get('permission');
        $accessLevel = $permission->getAccess($model->modelAlias);

        if (array_key_exists($accessLevel, $this->accessLevelMapping)) {
            $this->accessLevelMapping[$accessLevel]::applyACLByModel($model, $userId);
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
        $permission = $di->get('permission');

        foreach ($requestedRelationships as $rel) {
            $accessLevel = $permission->getAccess($rel);
            $relMeta = $relationship->getRelationship($rel);

            if (array_key_exists($accessLevel, $this->accessLevelMapping)) {
                // If the related model is group itself then apply access level 2.
                ($this->relIsGroup($relMeta)) && ($accessLevel = 2);
                $this->accessLevelMapping[$accessLevel]::applyACLByRel($model, $rel, $userId);
            } elseif ($accessLevel === '0') {
                // If access level is 0 then append 0 in the join condition to not retrieve related model.
                $model->getRelationship()->addRelConditions($rel, "0");
            }
        }
    }

    /**
     * This function is used to check whether the related model of the given relationship is
     * a group or not.
     *
     * @method relIsGroup
     * @param  $relMeta The relationship metadata.
     * @return boolean
     */
    private function relIsGroup($relMeta)
    {
        $di = \Phalcon\Di::getDefault();

        $isGroup = false;
        $relatedNamespace = (isset($relMeta['secondaryModel']))
                            ? $relMeta['secondaryModel']
                            : $relMeta['relatedModel'];

        $relatedModelName = Util::extractClassFromNamespace($relatedNamespace);
        $metadata = $di->get('metaManager')->getModelMeta($relatedModelName);

        if (isset($metadata['acl']['group'])) {
            $isGroup = true;
        }
        return $isGroup;
    }
}
