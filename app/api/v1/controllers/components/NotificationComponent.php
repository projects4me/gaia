<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Phalcon\Events\Event;
use Gaia\MVC\REST\Controllers\Components\Notifications\Config\NotificationConfig;
use Gaia\MVC\REST\Controllers\Components\Notifications\Services\RecipientService;

/**
 * Core component that handles notification events for model activities.
 * Listens for model lifecycle events (create, update, delete) and routes
 * them to the appropriate notification module based on the controller type.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\MVC\REST\Controllers\Components
 * @category Component
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NotificationComponent
{
    /**
     * Configuration for notification modules
     *
     * @var NotificationConfig
     */
    protected $config;
    
    /**
     * Service for managing notification recipients
     *
     * @var RecipientService
     */
    protected $recipientService;
    
    /**
     * Cache of instantiated notification module handlers
     *
     * @var array
     */
    protected $moduleHandlers = [];

    /**
     * Constructor - initializes notification configuration and recipient service
     */
    public function __construct()
    {
        $this->config = new NotificationConfig();
        $this->recipientService = new RecipientService();
    }

    /**
     * Loads and caches the notification module handler for a specific controller.
     *
     * @param string $controllerName The name of the controller
     * @return object|null The notification module handler or null if not found
     */
    protected function getModuleHandler($controllerName)
    {
        if (!isset($this->moduleHandlers[$controllerName])) {
            $moduleClass = $this->config->getModuleClass($controllerName);
            if (!$moduleClass) {
                return null;
            }

            $handlerClass = "\\Gaia\\MVC\\REST\\Controllers\\Components\\Notifications\\Modules\\{$moduleClass}";
            $this->moduleHandlers[$controllerName] = new $handlerClass($this->recipientService);
        }

        return $this->moduleHandlers[$controllerName];
    }

    /**
     * Event handler for model updates. Routes the event to the appropriate
     * notification module's onUpdate method.
     *
     * @param Event $event The event object
     * @param object $controller The controller instance
     * @param object $model The updated model
     * @return void
     * @throws \Gaia\Exception\Exception If an error occurs during notification processing
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        global $logger;
        try {
            $logger->debug('Notification::afterUpdate()');
            $controllerName = $controller->getControllerName();

            $handler = $this->getModuleHandler($controllerName);
            if (!$handler || !method_exists($handler, 'onUpdate')) {
                return;
            }

            $handler->onUpdate($model);
            $logger->debug('-Notification::afterUpdate()');
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in afterUpdate: ' . $e->getMessage());
        }
    }

    /**
     * Event handler for model creation. Routes the event to the appropriate
     * notification module's onCreate method.
     *
     * @param Event $event The event object
     * @param object $controller The controller instance
     * @param object $model The created model
     * @return void
     * @throws \Gaia\Exception\Exception If an error occurs during notification processing
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        global $logger;
        try {
            $logger->debug('Notification::afterCreate()');
            $controllerName = $controller->getControllerName();

            $handler = $this->getModuleHandler($controllerName);
            if (!$handler || !method_exists($handler, 'onCreate')) {
                return;
            }

            $handler->onCreate($model);
            $logger->debug('-Notification::afterCreate()');
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in afterCreate: ' . $e->getMessage());
        }
    }

    /**
     * Event handler for model deletion. Routes the event to the appropriate
     * notification module's onDelete method.
     *
     * @param Event $event The event object
     * @param object $controller The controller instance
     * @param object $model The deleted model
     * @return void
     * @throws \Gaia\Exception\Exception If an error occurs during notification processing
     */
    public function afterDelete(Event $event, $controller, $model)
    {
        global $logger;
        try {
            $logger->debug('Notification::afterDelete()');
            $controllerName = $controller->getControllerName();

            $handler = $this->getModuleHandler($controllerName);
            if (!$handler || !method_exists($handler, 'onDelete')) {
                return;
            }

            $handler->onDelete($model);
            $logger->debug('-Notification::afterDelete()');
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception('Error in afterDelete: ' . $e->getMessage());
        }
    }
}
