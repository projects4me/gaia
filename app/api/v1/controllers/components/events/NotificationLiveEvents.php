<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;

/**
 * This class publishes notification.created for a recipient. Nested recipient
 * saves do not go through REST, so this is invoked from the model hook rather
 * than attached as a controller $uses mixin.
 *
 * @class   NotificationLiveEvents
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class NotificationLiveEvents
{
    /**
     * Notification fields included on notification.created.
     *
     * @var  array
     * @type array
     */
    const NOTIFICATION_FIELDS = array(
        'description',
        'context',
        'createdUser',
        'createdUserName',
        'dateCreated',
    );

    /**
     * HTTP publisher used to POST the envelope to Hermes.
     *
     * @var  HermesPublisher
     * @type HermesPublisher
     */
    protected $publisher;

    /**
     * Creates the publisher, optionally with a test HTTP client.
     *
     * @param  HermesPublisher|null $publisher Publisher used to POST envelopes.
     * @method __construct
     * @return void
     */
    public function __construct($publisher = null)
    {
        $this->publisher = $publisher ?: new HermesPublisher();
    }

    /**
     * Publishes notification.created scoped to user:<recipientUserId>.
     *
     * @param  mixed $recipient    The Systemnotificationrecipient row.
     * @param  mixed $notification The parent Systemnotification.
     * @method publishCreated
     * @return array|null The posted envelope, or null when nothing was sent.
     */
    public function publishCreated($recipient, $notification)
    {
        if (
            !$recipient
            || !$notification
            || empty($recipient->userId)
            || empty($recipient->id)
            || empty($notification->id)
        ) {
            return null;
        }

        $changes = array();
        foreach (self::NOTIFICATION_FIELDS as $field) {
            if (isset($notification->$field)) {
                $changes[$field] = $notification->$field;
            }
        }

        return $this->publisher->publishDomainEvent(
            EventNames::NOTIFICATION_CREATED,
            'user:' . (string) $recipient->userId,
            'systemnotification',
            (string) $notification->id,
            $changes,
            array(
                'recipientId' => (string) $recipient->id,
                'recipientUserId' => (string) $recipient->userId,
            )
        );
    }
}
