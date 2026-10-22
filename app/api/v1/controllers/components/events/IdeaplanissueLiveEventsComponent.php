<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Phalcon\Events\Event;

/**
 * This component publishes idea plan issue create events.
 *
 * @class   IdeaplanissueLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class IdeaplanissueLiveEventsComponent extends LiveEventsComponent
{
    /**
     * Publishes idea.planissue.created after a successful persist.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted idea plan issue.
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
            EventNames::IDEA_PLANISSUE_CREATED,
            $model,
            'ideaplanissue',
            $id,
            array(
                'key' => isset($model->key) ? $model->key : null,
                'issueId' => isset($model->issueId) ? $model->issueId : null,
            ),
            array(
                'ideaId' => isset($model->ideaId) ? $model->ideaId : null,
                'ideaplanId' => isset($model->ideaplanId) ? $model->ideaplanId : null,
            )
        );
    }
}
