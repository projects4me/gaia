<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Phalcon\Events\Event;

/**
 * This component publishes user.access.changed for the affected user when a
 * role assignment is created, deleted, or moved to another user or role, so
 * Hermes and Prometheus re-check what that user may do.
 *
 * @class   UserroleLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class UserroleLiveEventsComponent
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
     * A new role assignment gives the user the role's permissions.
     *
     * @param  Event $event
     * @param  mixed $controller
     * @param  mixed $model
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        $this->publishFor($model);
    }

    /**
     * A deleted role assignment takes the role's permissions away.
     *
     * @param  Event $event
     * @param  mixed $controller
     * @param  mixed $model
     * @return void
     */
    public function afterDelete(Event $event, $controller, $model)
    {
        $this->publishFor($model);
    }

    /**
     * An update that changes userId or roleId affects the current user and,
     * when userId changed, the previous one.
     *
     * @param  Event $event
     * @param  mixed $controller
     * @param  mixed $model
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        $audit = ($model && isset($model->audit) && is_array($model->audit)) ? $model->audit : array();
        if (!isset($audit['userId']) && !isset($audit['roleId'])) {
            return;
        }

        $userIds = array();
        if (isset($model->userId)) {
            $userIds[] = $model->userId;
        }
        if (isset($audit['userId']['old'])) {
            $userIds[] = $audit['userId']['old'];
        }
        foreach (array_unique(array_filter($userIds)) as $userId) {
            $this->userAccess->publishAccessChanged($userId);
        }
    }

    /**
     * @param  mixed $model Userrole
     * @return void
     */
    protected function publishFor($model)
    {
        if (!$model || !isset($model->userId)) {
            return;
        }
        $this->userAccess->publishAccessChanged($model->userId);
    }
}
