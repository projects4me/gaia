<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;


/**
 * This behavior creates a new user defined variable that will be used by view in where clause as an identifier for model.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class modelIdentifierBehavior extends Behavior implements BehaviorInterface
{
    /**
     *  This function is called when ever an event is fired
     *
     * @param string                      $eventType
     * @param \Phalcon\Mvc\ModelInterface $model
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            $this->$eventType($model);
        }
        return $model;
    }

    /**
     * This function executes a query that will create a user defined variable.
     *
     * @param $model
     */
    protected function beforeQuery($model)
    {
        $GLOBALS['logger']->debug("Setting up new user defined variable");
        $model->getReadConnection()->query("select @modelId:= '{$model->query->modelId}'");
    }
}
