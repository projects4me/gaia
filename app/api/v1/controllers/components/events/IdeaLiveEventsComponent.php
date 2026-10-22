<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Phalcon\Events\Event;

/**
 * This component publishes idea create and update events.
 *
 * @class   IdeaLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class IdeaLiveEventsComponent extends LiveEventsComponent
{
    /**
     * Idea fields included on idea.created.
     *
     * @var  array
     * @type array
     */
    const CREATED_FIELDS = array(
        'title',
        'status',
    );

    /**
     * Idea fields whose change publishes idea.updated.
     *
     * @var  array
     * @type array
     */
    const UPDATED_FIELDS = array(
        'title',
        'description',
        'status',
    );

    /**
     * Publishes idea.created after a successful persist.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted idea.
     * @method afterCreate
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        $this->publishIdeaEvent(EventNames::IDEA_CREATED, $model, $this->pick(self::CREATED_FIELDS, $model));
    }

    /**
     * Publishes idea.updated when title, description or status changed.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted idea.
     * @method afterUpdate
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        $changes = $this->changesFromAudit($model, self::UPDATED_FIELDS);
        if (empty($changes)) {
            return;
        }

        $this->publishIdeaEvent(EventNames::IDEA_UPDATED, $model, $changes);
    }

    /**
     * Publishes an idea event with the idea id in meta.
     *
     * @param  string $eventName Allowlisted idea event name.
     * @param  mixed  $model     The idea model.
     * @param  array  $changes   Field values the client can apply.
     * @method publishIdeaEvent
     * @return void
     */
    protected function publishIdeaEvent($eventName, $model, array $changes)
    {
        $id = $this->modelId($model);
        if ($id === null) {
            return;
        }

        $this->publish($eventName, $model, 'idea', $id, $changes, array('ideaId' => $id));
    }
}
