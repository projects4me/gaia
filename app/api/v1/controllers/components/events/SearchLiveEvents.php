<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;

/**
 * This class publishes search.semantic.changed to Ares, which embeds the
 * record again. It is invoked from searchIndexBehavior (every write path) and
 * from the search reindex, not attached as a controller $uses mixin. The
 * payload is the search type, the REST resource and the record id; Ares reads
 * the text itself.
 *
 * @class   SearchLiveEvents
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class SearchLiveEvents
{
    /**
     * The scope Ares listens on.
     *
     * @var string
     */
    const SCOPE = 'service:ares';

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
     * Publishes search.semantic.changed scoped to service:ares.
     *
     * @param  string $type     The search type, e.g. issue.
     * @param  string $resource The REST resource, e.g. issue for /api/v1/issue.
     * @param  string $recordId The record id.
     * @return array|null The posted envelope, or null when nothing reached Hermes.
     */
    public function publishSemanticChanged($type, $resource, $recordId)
    {
        if (empty($type) || empty($resource) || empty($recordId)) {
            return null;
        }

        $envelope = $this->publisher->publishDomainEvent(
            EventNames::SEARCH_SEMANTIC_CHANGED,
            self::SCOPE,
            (string) $resource,
            (string) $recordId,
            array(
                'type' => (string) $type,
                'resource' => (string) $resource,
                'recordId' => (string) $recordId,
            )
        );

        // The publisher fails open; the reindex counts an event Hermes did not take as failed
        return $envelope !== null && $this->publisher->wasDelivered() ? $envelope : null;
    }
}
