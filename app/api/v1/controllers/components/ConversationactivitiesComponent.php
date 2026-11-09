<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use Phalcon\Events\Event;
use Gaia\Libraries\Conversations\RoomActivityRecorder;

/**
 * Records the activities of a conversation room after it is created or updated.
 *
 * It only forwards the controller events to RoomActivityRecorder.
 *
 * @class    ConversationactivitiesComponent
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\MVC\REST\Controllers\Components
 * @category Component
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ConversationactivitiesComponent
{
    /**
     * Records the "thread started" activity of a new room.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted conversation room.
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        (new RoomActivityRecorder())->recordCreated($model);
    }

    /**
     * Records the activities of a room's edits and linked-issue changes.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The updated conversation room.
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        (new RoomActivityRecorder())->recordUpdated($model);
    }
}
