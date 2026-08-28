<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Phalcon\Events\Event;

/**
 * This component publishes conversation-room vote add and remove events.
 * Votes on issues or wiki are ignored.
 *
 * @class   ConversationVoteLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class ConversationVoteLiveEventsComponent extends LiveEventsComponent
{
    /**
     * Vote fields included on conversation vote envelopes.
     *
     * @var  array
     * @type array
     */
    const VOTE_FIELDS = array(
        'vote',
        'relatedTo',
        'relatedId',
        'createdUser',
        'createdUserName',
    );

    /**
     * Publishes conversation.vote.added after a successful persist.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted vote.
     * @method afterCreate
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        $this->publishVoteEvent(
            EventNames::CONVERSATION_VOTE_ADDED,
            $model,
            $this->pick(self::VOTE_FIELDS, $model)
        );
    }

    /**
     * Publishes conversation.vote.removed after a successful delete.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The deleted vote.
     * @method afterDelete
     * @return void
     */
    public function afterDelete(Event $event, $controller, $model)
    {
        $this->publishVoteEvent(
            EventNames::CONVERSATION_VOTE_REMOVED,
            $model,
            array(
                'relatedTo' => isset($model->relatedTo) ? $model->relatedTo : null,
                'relatedId' => isset($model->relatedId) ? $model->relatedId : null,
            )
        );
    }

    /**
     * Publishes a conversation vote event when relatedTo is a conversation room.
     *
     * @param  string $eventName Allowlisted vote event name.
     * @param  mixed  $model     The vote model.
     * @param  array  $changes   Field values the client can apply.
     * @method publishVoteEvent
     * @return void
     */
    protected function publishVoteEvent($eventName, $model, array $changes)
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
            'vote',
            $id,
            $changes,
            array(
                'conversationId' => $relatedId,
                'relatedId' => $relatedId,
            )
        );
    }
}
