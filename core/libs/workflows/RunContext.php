<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * One run of a workflow as its steps see it: the trigger payload, the
 * workflow, the user its changes are made as, and the records it changed
 * (so their events can start other workflows, see LoopGuard).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RunContext
{
    /**
     * @var string
     */
    public $runId;

    /**
     * @var array {id, name, version}
     */
    public $workflow;

    /**
     * The user the workflow's changes to existing records are made as: the
     * workflow's owner, or whoever created it.
     *
     * @var string
     */
    public $actingUserId;

    /**
     * @var array The trigger payload.
     */
    public $payload;

    /**
     * @var array [model, event] pairs, one per record a step created or changed.
     */
    private $changes = [];

    /**
     * @param string $runId
     * @param array  $workflow     {id, name, version}
     * @param string $actingUserId
     * @param array  $payload
     */
    public function __construct($runId, array $workflow, $actingUserId, array $payload)
    {
        $this->runId = (string) $runId;
        $this->workflow = $workflow;
        $this->actingUserId = (string) $actingUserId;
        $this->payload = $payload;
    }

    /**
     * The values expressions read: the payload plus `workflow` and `run`.
     *
     * @return array
     */
    public function values()
    {
        return $this->payload + ['workflow' => $this->workflow, 'run' => ['id' => $this->runId]];
    }

    /**
     * The project the run is for.
     *
     * @return string|null
     */
    public function projectId()
    {
        return empty($this->payload['projectId']) ? null : (string) $this->payload['projectId'];
    }

    /**
     * The record the event is about, as `[model name, id]`, or null.
     *
     * @return array|null
     */
    public function subject()
    {
        $resource = isset($this->payload['resource']) ? $this->payload['resource'] : null;
        if (!is_array($resource) || empty($resource['type']) || empty($resource['id'])) {
            return null;
        }

        return [ucfirst((string) $resource['type']), (string) $resource['id']];
    }

    /**
     * Remember a record a step created or changed.
     *
     * @param  \Phalcon\Mvc\ModelInterface $record
     * @param  string                      $on TriggerEventCatalog::ON_CREATE or ON_UPDATE.
     * @return void
     */
    public function changed($record, $on)
    {
        $this->changes[] = [$record, $on];
    }

    /**
     * The records changed since the last call.
     *
     * @return array [model, event] pairs
     */
    public function takeChanges()
    {
        $changes = $this->changes;
        $this->changes = [];

        return $changes;
    }
}
