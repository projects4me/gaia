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
     * 
     * @param string $eventType
     * @param Phalcon\Mvc\ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, \Phalcon\Mvc\ModelInterface $model)
    {
        if (method_exists($this, $eventType))
        {
            $this->$eventType($model);
        }
    }

    /**
     * 
     * @param Phalcon\Mvc\ModelInterface $model
     * @return Phalcon\Mvc\ModelInterface
     */
    protected function beforeCreate(&$model)
    {
        $userName = $GLOBALS['currentUser']->id;

        //Store in a log the username - event type and primary key
        file_put_contents('blamable-log.txt', $userName.' '.$eventType.' '.$model->id."\r",FILE_APPEND);
        
        return $model;
    }

    /**
     * 
     * @param Phalcon\Mvc\ModelInterface $model
     * @return Phalcon\Mvc\Model\Criteria
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