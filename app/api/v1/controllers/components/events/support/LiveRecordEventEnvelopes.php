<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events\Support;

use Gaia\Libraries\Workflows\RecordEventEnvelopes;

/**
 * Builds a record event's envelope the way a live write does: the record's
 * project from ProjectIdResolver, the envelope from LiveEventEnvelope.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\REST\Controllers\Components\Events\Support
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LiveRecordEventEnvelopes implements RecordEventEnvelopes
{
    /**
     * @var ProjectIdResolver
     */
    private $resolver;

    /**
     * @param ProjectIdResolver|null $resolver
     */
    public function __construct(ProjectIdResolver $resolver = null)
    {
        $this->resolver = $resolver ?: new ProjectIdResolver();
    }

    /**
     * {@inheritdoc}
     */
    public function envelope($eventName, $record, $resourceType, $resourceId)
    {
        return LiveEventEnvelope::build($eventName, $this->resolver->resolve($record), $resourceType, $resourceId);
    }
}
