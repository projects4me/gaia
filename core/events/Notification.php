<?php

namespace Gaia\Events;

/**
 * This class is responsible for managing the events of type notification in the system.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Events
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Notification
{
    /**
     * List of listeners.
     *
     * @var array
     */
    protected $listeners = ['Email'];

    /**
     * This function is used to attach the events to the event manager.
     *
     * @param  \Phalcon\Events\Manager $eventManager
     * @return void
     */
    public function attachEvents($eventManager)
    {
        foreach ($this->listeners as $component) {
            $listener = 'Gaia\Events\Notification\\' . $component;
            $eventManager->attach('notifications', new $listener());
        }
    }
}
