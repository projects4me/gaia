<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;


/**
 * Description of aclBehavior
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 */
class aclBehavior extends Behavior implements BehaviorInterface
{
    /**
     * This function is used to notify
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
     * This function is called before the creation of a model
     *
     * @param ModelInterface $model
     * @return ModelInterface
     */
    protected function beforeCreate(&$model)
    {
        $userName = $GLOBALS['currentUser']->id;

        //Store in a log the username - event type and primary key
        file_put_contents('blamable-log.txt', $userName.' '.$eventType.' '.$model->id."\r",FILE_APPEND);
        
        return $model;
    }

    /**
     * This function is called before a query is executed
     *
     * @param ModelInterface $model
     * @return void
     */
    protected function beforeQuery($model)
    {
        $userName = $GLOBALS['currentUser']->id;
        if ($model instanceof Projects)
        {
            //$model->query->andWhere("Projects.name='Kya'");
        }
    }
}