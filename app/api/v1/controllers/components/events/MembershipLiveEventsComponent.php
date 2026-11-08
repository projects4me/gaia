<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Phalcon\Events\Event;

/**
 * This component publishes user.access.changed for the member when a
 * project membership is created or deleted, so Hermes re-checks which
 * projects that user's sockets may follow.
 *
 * @class   MembershipLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class MembershipLiveEventsComponent
{
    /**
     * Publishes the user-scoped access event.
     *
     * @var UserAccessLiveEventsComponent
     */
    protected $userAccess;

    /**
     * @param UserAccessLiveEventsComponent|null $userAccess
     */
    public function __construct($userAccess = null)
    {
        $this->userAccess = $userAccess ?: new UserAccessLiveEventsComponent();
    }

    /**
     * A new membership gives the user access to a project.
     *
     * @param  Event $event
     * @param  mixed $controller
     * @param  mixed $model
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        $this->publishForMember($model);
    }

    /**
     * A deleted membership takes the user's access to a project away.
     *
     * @param  Event $event
     * @param  mixed $controller
     * @param  mixed $model
     * @return void
     */
    public function afterDelete(Event $event, $controller, $model)
    {
        $this->publishForMember($model);
    }

    /**
     * @param  mixed $model Membership
     * @return void
     */
    protected function publishForMember($model)
    {
        if (!$model || !isset($model->userId)) {
            return;
        }
        $this->userAccess->publishAccessChanged($model->userId);
    }
}
