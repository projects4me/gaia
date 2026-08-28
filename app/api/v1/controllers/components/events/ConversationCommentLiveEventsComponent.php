<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Phalcon\Events\Event;

/**
 * This component publishes conversation-room comment create, update, and
 * delete events. Issue and wiki comments are ignored.
 *
 * @class   ConversationCommentLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class ConversationCommentLiveEventsComponent extends LiveEventsComponent
{
    /**
     * Comment fields included on conversation comment envelopes.
     *
     * @var  array
     * @type array
     */
    const COMMENT_FIELDS = array(
        'comment',
        'relatedTo',
        'relatedId',
        'createdUser',
        'createdUserName',
        'dateCreated',
        'dateModified',
    );

    /**
     * Publishes conversation.comment.created after a successful persist.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted comment.
     * @method afterCreate
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        $this->publishCommentEvent(EventNames::CONVERSATION_COMMENT_CREATED, $model, $this->pick(self::COMMENT_FIELDS, $model));
    }

    /**
     * Publishes conversation.comment.updated from the comment audit diff.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted comment.
     * @method afterUpdate
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        $this->publishCommentEvent(
            EventNames::CONVERSATION_COMMENT_UPDATED,
            $model,
            $this->changesFromAudit($model, self::COMMENT_FIELDS)
        );
    }

    /**
     * Publishes conversation.comment.deleted after a successful delete.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The deleted comment.
     * @method afterDelete
     * @return void
     */
    public function afterDelete(Event $event, $controller, $model)
    {
        $this->publishCommentEvent(
            EventNames::CONVERSATION_COMMENT_DELETED,
            $model,
            array(
                'relatedTo' => isset($model->relatedTo) ? $model->relatedTo : null,
                'relatedId' => isset($model->relatedId) ? $model->relatedId : null,
            )
        );
    }

    /**
     * Publishes a conversation comment event when relatedTo is a conversation room.
     *
     * @param  string $eventName Allowlisted comment event name.
     * @param  mixed  $model     The comment model.
     * @param  array  $changes   Field values the client can apply.
     * @method publishCommentEvent
     * @return void
     */
    protected function publishCommentEvent($eventName, $model, array $changes)
    {
        if (!$this->isConversationRelated($model)) {
            return;
        }

        $id = $this->modelId($model);
        if ($id === null) {
            return;
        }

        $relatedId = isset($model->relatedId) ? $model->relatedId : null;
        $this->publish(
            $eventName,
            $model,
            'comment',
            $id,
            $changes,
            array(
                'conversationId' => $relatedId,
                'relatedId' => $relatedId,
            )
        );
    }
}
