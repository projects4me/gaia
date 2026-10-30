<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Fires the schedule triggers due in a minute: each Active workflow whose
 * schedule node's cron expression matches the minute is handed to the
 * engine. A task calls it once a minute; cron expressions are read in UTC.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScheduleTriggerRunner
{
    /**
     * @var ActiveTriggerNodes
     */
    private $nodes;

    /**
     * @var WorkflowEngine
     */
    private $engine;

    /**
     * @var TriggerPayload
     */
    private $payloads;

    /**
     * @param ActiveTriggerNodes|null $nodes
     * @param WorkflowEngine|null     $engine
     * @param TriggerPayload|null     $payloads
     */
    public function __construct(
        ActiveTriggerNodes $nodes = null,
        WorkflowEngine $engine = null,
        TriggerPayload $payloads = null
    ) {
        $this->nodes = $nodes ?: new ActiveTriggerNodes();
        $this->engine = $engine ?: new LoggedWorkflowEngine();
        $this->payloads = $payloads ?: new TriggerPayload();
    }

    /**
     * Starts each workflow whose schedule is due in the minute of $at.
     *
     * @param  \DateTimeInterface $at Any time in the minute; read in UTC.
     * @return int The number of runs started.
     */
    public function run(\DateTimeInterface $at)
    {
        $utc = new \DateTimeImmutable('@' . $at->getTimestamp());
        $minute = $utc->setTime((int) $utc->format('G'), (int) $utc->format('i'));

        $started = 0;
        foreach ($this->nodes->ofKind(TriggerEventCatalog::SCHEDULE) as $node) {
            $cron = isset($node['config']['cron']) ? $node['config']['cron'] : null;
            if (!$this->isDue($cron, $minute)) {
                continue;
            }
            $payload = $this->payloads->forSchedule(create_guid(), $cron, $minute);
            $this->engine->start($node['workflowId'], $node['nodeId'], $payload);
            $started++;
        }

        return $started;
    }

    /**
     * Whether the cron expression matches the minute. A node saved before
     * its expression was checked may hold an invalid one; it never fires.
     *
     * @param  mixed              $cron
     * @param  \DateTimeInterface $minute
     * @return bool
     */
    private function isDue($cron, \DateTimeInterface $minute)
    {
        try {
            return (new CronExpression($cron))->matches($minute);
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
