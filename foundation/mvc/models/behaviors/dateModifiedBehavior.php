<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;


/**
 * This behavior serves the purpose of maintaining the date modified date timestamp.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 */
class dateModifiedBehavior extends Behavior implements BehaviorInterface
{
    /**
     * This function is called whenever an event is triggered
     * from a model e.g. beforeCreate or afterUpdate
     *
     * @param string $eventType
     * @param ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType))
        {
            $this->$eventType($model);
        }
    }

    /**
     * This function is called before a model is to be changed
     *
     * @param ModelInterface $model
     * @return ModelInterface
     */
    protected function beforeUpdate(&$model)
    {
        $model->dateModified = gmdate('Y-m-d H:i:s');
    }

    /**
     * This function is called before a model is created
     *
     * @param ModelInterface $model
     * @return ModelInterface
     */
    protected function beforeCreate(&$model)
    {
        $model->dateModified = gmdate('Y-m-d H:i:s');
    }

}