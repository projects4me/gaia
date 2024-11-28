<?php

namespace Gaia\Events;

/**
 * This class is responsible for managing the events in the system.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Events
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class EventManager
{
    /**
     * This is the instance of the event manager
     *
     * @var \Phalcon\Events\Manager
     */
    private static $instance;

    /**
     * This is the list of events that are available
     *
     * @var array
     */
    protected $events = ['Notification'];

    /**
     * This is the function that returns the instance of the event manager
     *
     * @return \Phalcon\Events\Manager
     */
    public static function getPhalconEventManager()
    {
        if (!self::$instance) {
            self::$instance = new \Phalcon\Events\Manager();
        }
        return self::$instance;
    }

    /**
     * This function is used to attach the events to the event manager.
     *
     * @return void
     */
    public function attachEvents()
    {
        $events = $this->getEvents();
        foreach ($events as $event) {
            $eventClass = 'Gaia\Events\\' . $event;
            $eventInstance = new $eventClass();
            $eventInstance->attachEvents($this->getPhalconEventManager());
        }

        // Set phalcon's event manager on the dependency injector.
        $di = \Phalcon\Di::getDefault();
        $di->set('eventsManager', self::getPhalconEventManager());
    }

    /**
     * This function is used to get the list of events.
     *
     * @return array
     */
    public function getEvents()
    {
        return $this->events;
    }
}
