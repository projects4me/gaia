<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface,
Phalcon\Mvc\Model\BehaviorInterface,
Phalcon\Mvc\Model\Behavior,
Gaia\Libraries\Utils\Util;

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
     * @param string $eventType
     * @param ModelInterface $model
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
     * @param ModelInterface $model
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
     * @param ModelInterface $model
     * @return void
     */
    protected function beforeJoins($model)
    {
        $relationships = $model->getRelationship()->getRequestedRelationships();
        $userId = $GLOBALS['currentUser']->id;

        $di = \Phalcon\Di::getDefault();
        $permission = $di->get('permission');

        foreach ($relationships as $rel) {
            $accessLevel = $permission->getAccess($rel);

            if ($accessLevel < 3) {
                $this->accessLevelMapping[$accessLevel]::applyACLByRel($model, $rel, $userId);
            }
        }
    }
}
