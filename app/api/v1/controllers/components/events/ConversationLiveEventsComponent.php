<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Phalcon\Events\Event;

/**
 * This component publishes conversation-room creation events.
 *
 * @class   ConversationLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class ConversationLiveEventsComponent extends LiveEventsComponent
{
    /**
     * Conversation fields included on conversation.created.
     *
     * @var  array
     * @type array
     */
    const CREATED_FIELDS = array(
        'subject',
        'description',
        'roomType',
        'projectId',
        'issueId',
        'createdUser',
        'createdUserName',
    );

    /**
     * Publishes conversation.created after a successful persist.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted conversation room.
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
            EventNames::CONVERSATION_CREATED,
            $model,
            'conversationroom',
            $id,
            $this->pick(self::CREATED_FIELDS, $model),
            array(
                'issueId' => isset($model->issueId) ? $model->issueId : null,
            )
        );
    }
}
