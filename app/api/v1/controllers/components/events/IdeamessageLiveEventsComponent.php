<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Phalcon\Events\Event;

/**
 * This component publishes idea message create events. The message body is
 * never sent; subscribers re-read the message through the API.
 *
 * @class   IdeamessageLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class IdeamessageLiveEventsComponent extends LiveEventsComponent
{
    /**
     * Publishes idea.message.created after a successful persist.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted idea message.
     * @method afterCreate
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        $id = $this->modelId($model);
        if ($id === null) {
            return;
        }

        $this->publish(
            EventNames::IDEA_MESSAGE_CREATED,
            $model,
            'ideamessage',
            $id,
            array('kind' => isset($model->kind) ? $model->kind : null),
            array('ideaId' => isset($model->ideaId) ? $model->ideaId : null)
        );
    }
}
