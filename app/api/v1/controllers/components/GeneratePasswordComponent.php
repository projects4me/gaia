<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Phalcon\Events\Event;

/**
 * This component generates a random plain-text password and assigns it to the
 * model before it is persisted. The encryptPasswordBehavior on the User model
 * will then hash the value using bcrypt on its own beforeCreate hook.
 *
 * @class   GeneratePasswordComponent
 * @package Gaia\MVC\REST\Controllers\Components
 */
class GeneratePasswordComponent
{
    private $generatedPassword;

    /**
     * Assigns a randomly generated password to the model before it is created.
     *
     * @param  Event                               $event      The event object.
     * @param  \Gaia\Core\MVC\REST\Controllers\RestController $controller The controller that fired the event.
     * @param  \Gaia\Core\MVC\Models\Model         $model      The model being created.
     * @method beforeCreate
     * @return void
     */
    public function beforeCreate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.GeneratePassword::beforeCreate()');

        $model->password = bin2hex(random_bytes(8));
        $di = \Phalcon\Di::getDefault();
        $this->generatedPassword = $model->password;

        $logger->debug('-Gaia.Controller.Component.GeneratePassword::beforeCreate()');
    }

    /**
     * Sends the generated password to the user via email.
     *
     * @param  Event                               $event      The event object.
     * @param  \Gaia\Core\MVC\REST\Controllers\RestController $controller The controller that fired the event.
     * @param  \Gaia\Core\MVC\Models\Model         $model      The model being created.
     * @method afterCreate
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.GeneratePassword::afterCreate()');

        $di = \Phalcon\Di::getDefault();
        $eventManager = $di->get('eventsManager');
        $eventManager->fire('notifications:emailPassword', $model, $this->generatedPassword);
    }
}
