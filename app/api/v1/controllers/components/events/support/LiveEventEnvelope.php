<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events\Support;

use function Gaia\Libraries\Utils\create_guid;

/**
 * This class builds the V2 Hermes domain-event envelope Gaia posts to
 * POST /publish.
 *
 * @class   LiveEventEnvelope
 * @package Gaia\MVC\REST\Controllers\Components\Events\Support
 */
class LiveEventEnvelope
{
    /**
     * Envelope schema version accepted by Hermes.
     *
     * @var int
     */
    const SCHEMA_VERSION = 2;

    /**
     * Builds a V2 envelope. actorId is the current user or null.
     *
     * @param  string $eventName    Allowlisted event name.
     * @param  string $projectId    Project id, or user:<userId> for notifications.
     * @param  string $resourceType Resource type string.
     * @param  string $resourceId   Persisted resource id.
     * @param  array  $changes      Field values the client can apply.
     * @param  array  $meta         Extra metadata merged with actorName.
     * @method build
     * @return array
     */
    public static function build(
        $eventName,
        $projectId,
        $resourceType,
        $resourceId,
        array $changes = array(),
        array $meta = array()
    ) {
        global $currentUser;

        $actorId = isset($currentUser->id) ? (string) $currentUser->id : null;
        $actorName = isset($currentUser->name) ? $currentUser->name : null;

        return array(
            'schemaVersion' => self::SCHEMA_VERSION,
            'eventId' => create_guid(),
            'eventName' => $eventName,
            'occurredAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'projectId' => (string) $projectId,
            'resource' => array(
                'type' => (string) $resourceType,
                'id' => (string) $resourceId,
            ),
            'actorId' => $actorId,
            'changes' => $changes,
            'meta' => array_merge(
                array(
                    'actorName' => $actorName,
                ),
                $meta
            ),
        );
    }
}
