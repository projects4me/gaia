<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\MVC\Models\User;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior adds the time spent by user on issues.
 *
 * @author Rana Nouman <ranamnouman@yahoo.com>
 */
class timeSpentBehavior extends Behavior implements BehaviorInterface
{
    /**
     *  This function is called when ever an event is fired
     *
     * @param string $eventType
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
     * This function add logged time value to timeSpent property of user 
     * model, after the timelog model is created.
     *
     * @param $model
     */
    public function afterCreate($model)
    {
        if (!empty($model->createdUser)) {
            $user= User::findFirst("id = '".$model->createdUser."'");
            $timeSpentInMinutes = $this->getTimeInMinutes($model);
            $user->timeSpent += $timeSpentInMinutes;
            $user->save();
        }
    }

    /**
     * This function is used to calculate total time in minutes.
     *
     * @param $model
     * @return int
     */
    private function getTimeInMinutes($model) {
        return ($model->minutes + ($model->hours * 60) + ($model->days * 8 * 60));
    }
}
