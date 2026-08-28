<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Phalcon\Events\Event;

/**
 * This component publishes milestone creation and completion events.
 *
 * @class   MilestoneLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class MilestoneLiveEventsComponent extends LiveEventsComponent
{
    /**
     * Milestone fields included on milestone.created.
     *
     * @var  array
     * @type array
     */
    const CREATED_FIELDS = array(
        'name',
        'status',
        'startDate',
        'endDate',
        'projectId',
        'milestoneType',
    );

    /**
     * Publishes milestone.created after a successful persist.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted milestone.
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
            EventNames::MILESTONE_CREATED,
            $model,
            'milestone',
            $id,
            $this->pick(self::CREATED_FIELDS, $model)
        );
    }

    /**
     * Publishes milestone.completed when status transitions into completed.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted milestone.
     * @method afterUpdate
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        $id = $this->modelId($model);
        if ($id === null) {
            return;
        }

        $newStatus = $this->auditValue($model, 'status', 'new');
        $oldStatus = $this->auditValue($model, 'status', 'old');
        if ($newStatus !== 'completed' || $oldStatus === 'completed') {
            return;
        }

        $this->publish(
            EventNames::MILESTONE_COMPLETED,
            $model,
            'milestone',
            $id,
            array('status' => $newStatus)
        );
    }
}
