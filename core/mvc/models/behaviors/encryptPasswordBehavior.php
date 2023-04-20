<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior encrypts user password using bcrypt alogrithm.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class encryptPasswordBehavior extends Behavior implements BehaviorInterface
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
        if (method_exists($this, $eventType)) {
            $this->$eventType($model);
        }
    }

    /**
     * This function is called before a model is saved.
     *
     * @param ModelInterface $model
     * @return ModelInterface
     */
    protected function beforeSave(&$model)
    {
        $model->password = password_hash($model->password, PASSWORD_DEFAULT);
    }
}
