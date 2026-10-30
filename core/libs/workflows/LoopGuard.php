<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Keeps workflows from triggering themselves. The events of the records a
 * run changes carry the run (`workflowRun` in the payload: its id, the
 * workflows of the run and of the runs that led to it, and the depth). A
 * workflow already in that chain does not start again, and no run starts
 * deeper than MAX_DEPTH.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LoopGuard
{
    /**
     * The payload key that carries the run an event came from.
     */
    public const KEY = 'workflowRun';

    /**
     * How deep runs started by other runs may nest; a run started by a
     * person or a schedule has depth 0.
     */
    public const MAX_DEPTH = 3;

    /**
     * Why the workflow must not start for this event, or null when it may.
     *
     * @param  string $workflowId
     * @param  array  $payload
     * @return string|null
     */
    public function refusal($workflowId, array $payload)
    {
        if (in_array((string) $workflowId, self::workflowIds($payload), true)) {
            return "workflow {$workflowId} changed this record itself";
        }
        if (self::depth($payload) > self::MAX_DEPTH) {
            return 'runs nest deeper than ' . self::MAX_DEPTH;
        }

        return null;
    }

    /**
     * What the events of a run's changes carry.
     *
     * @param  RunContext $context
     * @return array
     */
    public function carried(RunContext $context)
    {
        return [
            'id' => $context->runId,
            'workflowIds' => array_values(array_unique(array_merge(
                self::workflowIds($context->payload),
                [(string) $context->workflow['id']]
            ))),
            'depth' => self::depth($context->payload) + 1,
        ];
    }

    /**
     * The run whose change caused the event, if any.
     *
     * @param  array $payload
     * @return string|null
     */
    public static function parentRunId(array $payload)
    {
        return isset($payload[self::KEY]['id']) ? (string) $payload[self::KEY]['id'] : null;
    }

    /**
     * @param  array $payload
     * @return int
     */
    public static function depth(array $payload)
    {
        return isset($payload[self::KEY]['depth']) ? (int) $payload[self::KEY]['depth'] : 0;
    }

    /**
     * @param  array $payload
     * @return string[]
     */
    private static function workflowIds(array $payload)
    {
        $ids = isset($payload[self::KEY]['workflowIds']) ? $payload[self::KEY]['workflowIds'] : [];

        return is_array($ids) ? array_map('strval', $ids) : [];
    }
}
