<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Builds the live event envelope of an event about a record, the same one a
 * write of the record would raise (event name and id, the record's project,
 * the resource). A test run builds its trigger payload on it.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface RecordEventEnvelopes
{
    /**
     * @param  string                       $eventName
     * @param  \Phalcon\Mvc\ModelInterface  $record
     * @param  string                       $resourceType
     * @param  string                       $resourceId
     * @return array The envelope; its projectId is the record's project, or null.
     */
    public function envelope($eventName, $record, $resourceType, $resourceId);
}
