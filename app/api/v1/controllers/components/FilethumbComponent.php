<?php

namespace Foundation\Mvc\Controller\Component;

use \Phalcon\Events\Event as Event;


class filethumbComponent
{
    public function afterRead(Event $event, $controller)
    {
        if (isset($controller->finalData['data']['relationships']['files'])) {
            unset($controller->finalData['data']['relationships']['files']);
        }
    }
}