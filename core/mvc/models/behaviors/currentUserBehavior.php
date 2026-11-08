<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;


/**
 * This behavior creates a new user defined variable that will be used by view in where clause as an identifier for current user.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class currentUserBehavior extends Behavior implements BehaviorInterface
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
     * A service request has no current user (D-066); the variable is then
     * empty, so nothing keyed on it matches.
     *
     * @param $model
     */
    protected function beforeQuery($model)
    {
        global $currentUser;

        $userId = isset($currentUser->id) ? (string) $currentUser->id : '';
        $query = $model->getDI()->get('dialect')->setCurrentUserIdentifier($userId);
        $model->getReadConnection()->query($query);
    }
}