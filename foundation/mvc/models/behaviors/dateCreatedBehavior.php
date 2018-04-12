<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;


/**
 * This behavior serves the purpose of maintaining the date created timestamp.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 */
class dateCreatedBehavior extends Behavior implements BehaviorInterface
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
    protected function beforeValidationOnUpdate(&$model)
    {
        if ($model->isChanged) {
            if (isset($model->audit['dateCreated'])){
                $model->dateCreated = $model->audit['dateCreated']['old'];
            }
        }
    }

    /**
     * This function is called before a model is created
     *
     * @param ModelInterface $model
     * @return ModelInterface
     */
    protected function beforeValidationOnCreate(&$model)
    {
        $model->dateCreated = gmdate('Y-m-d H:i:s');
    }

}