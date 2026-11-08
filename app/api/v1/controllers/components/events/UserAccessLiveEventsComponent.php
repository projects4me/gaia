<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Phalcon\Events\Event;

/**
 * This component publishes user.access.changed for a user, scoped to
 * user:<userId> (D-006), so Hermes re-checks that user's sockets. The event
 * carries only the user id. `UserController` fires `rest:afterAccessChange`
 * after an account status change has been saved (and, for a single-user
 * PATCH deactivation, after the user's sessions were revoked; the
 * collection PATCH does not revoke sessions).
 *
 * @class   UserAccessLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class UserAccessLiveEventsComponent extends LiveEventsComponent
{
    /**
     * Resource type on the envelope.
     *
     * @var string
     */
    const RESOURCE_TYPE = 'user';

    /**
     * Publish user.access.changed for the user whose access changed.
     *
     * @param  Event  $event
     * @param  mixed  $controller
     * @param  string $userId
     * @return void
     */
    public function afterAccessChange(Event $event, $controller, $userId)
    {
        $this->publishAccessChanged($userId);
    }

    /**
     * Publish user.access.changed to user:<userId>. Fails open.
     *
     * @param  string|null $userId
     * @return void
     */
    public function publishAccessChanged($userId)
    {
        if ($this->isBlank($userId)) {
            return;
        }

        $userId = (string) $userId;
        $this->publish(
            EventNames::USER_ACCESS_CHANGED,
            null,
            self::RESOURCE_TYPE,
            $userId,
            array(),
            array(),
            'user:' . $userId
        );
    }
}
