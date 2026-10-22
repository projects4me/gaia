<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Phalcon\Events\Event;

/**
 * This component publishes idea plan create and status events. The plan body
 * is never sent; subscribers re-read the plan through the API.
 *
 * @class   IdeaplanLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class IdeaplanLiveEventsComponent extends LiveEventsComponent
{
    /**
     * Publishes idea.plan.created after a successful persist.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted idea plan.
     * @method afterCreate
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        $this->publishPlanEvent(EventNames::IDEA_PLAN_CREATED, $model, array(
            'version' => isset($model->version) ? $model->version : null,
            'status' => isset($model->status) ? $model->status : null,
        ));
    }

    /**
     * Publishes idea.plan.status.changed when status or error changed.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted idea plan.
     * @method afterUpdate
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        if (empty($this->changesFromAudit($model, array('status', 'error')))) {
            return;
        }

        $this->publishPlanEvent(EventNames::IDEA_PLAN_STATUS_CHANGED, $model, array(
            'status' => isset($model->status) ? $model->status : null,
            'error' => isset($model->error) ? $model->error : null,
        ));
    }

    /**
     * Publishes an idea plan event with the idea id and plan version in meta.
     *
     * @param  string $eventName Allowlisted idea plan event name.
     * @param  mixed  $model     The idea plan model.
     * @param  array  $changes   Field values the client can apply.
     * @method publishPlanEvent
     * @return void
     */
    protected function publishPlanEvent($eventName, $model, array $changes)
    {
        $id = $this->modelId($model);
        if ($id === null) {
            return;
        }

        $this->publish($eventName, $model, 'ideaplan', $id, $changes, array(
            'ideaId' => isset($model->ideaId) ? $model->ideaId : null,
            'version' => isset($model->version) ? $model->version : null,
        ));
    }
}
