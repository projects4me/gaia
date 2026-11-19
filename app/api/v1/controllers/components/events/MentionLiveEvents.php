<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;

/**
 * This class publishes user.mentioned for the mentioned user. Mention rows
 * are saved by MentionRecorder, not through REST, so it is invoked from the
 * model hook rather than attached as a controller $uses mixin. Ids only.
 *
 * @class   MentionLiveEvents
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class MentionLiveEvents
{
    /**
     * HTTP publisher used to POST the envelope to Hermes.
     *
     * @var HermesPublisher
     */
    protected $publisher;

    /**
     * @param HermesPublisher|null $publisher Publisher used to POST envelopes.
     */
    public function __construct($publisher = null)
    {
        $this->publisher = $publisher ?: new HermesPublisher();
    }

    /**
     * Publishes user.mentioned scoped to user:<mentionedUserId>.
     *
     * @param  mixed $mention The Mention row.
     * @return array|null The posted envelope, or null when nothing was sent.
     */
    public function publishMentioned($mention)
    {
        if (!$mention || empty($mention->id) || empty($mention->userId)) {
            return null;
        }

        $meta = array(
            'mentionId' => (string) $mention->id,
            'relatedTo' => (string) $mention->relatedTo,
            'relatedId' => (string) $mention->relatedId,
        );
        if (!empty($mention->projectId)) {
            $meta['projectId'] = (string) $mention->projectId;
        }
        if (!empty($mention->createdUser)) {
            $meta['actorId'] = (string) $mention->createdUser;
        }

        return $this->publisher->publishDomainEvent(
            EventNames::USER_MENTIONED,
            'user:' . (string) $mention->userId,
            'user',
            (string) $mention->userId,
            array(),
            $meta
        );
    }
}
