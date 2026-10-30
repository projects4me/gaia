<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workflows;

use Gaia\Libraries\Workflows\ActiveTriggerNodes;
use Gaia\Libraries\Workflows\ScheduleTriggerRunner;
use Gaia\Libraries\Workflows\TriggerDispatcher;
use Gaia\Libraries\Workflows\TriggerPayload;
use Gaia\Libraries\Workflows\WorkflowEngine;
use Gaia\Libraries\Workflows\WorkflowScope;
use PHPUnit\Framework\TestCase;

/**
 * Tests handing events and due schedules to the workflows they start, and
 * the payload a workflow starts with. Active trigger nodes and the engine
 * are stubbed, so no database is needed.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TriggerDispatchTest extends TestCase
{
    /**
     * @var array[] Runs the engine was asked to start: [workflowId, nodeId, payload].
     */
    private $started = [];

    public function testAnEventStartsTheWorkflowsWhoseScopeCoversItsProject()
    {
        $dispatcher = new TriggerDispatcher($this->nodes([
            'issue.created' => [
                $this->node('wf-no-scope', []),
                $this->node('wf-project-1', ['project-1']),
                $this->node('wf-projects-1-3', ['project-3', 'project-1']),
                $this->node('wf-project-2', ['project-2']),
            ],
        ]), $this->engine());

        $started = $dispatcher->dispatch(['eventName' => 'issue.created', 'projectId' => 'project-1']);

        $this->assertSame(2, $started);
        $this->assertSame(['wf-project-1', 'wf-projects-1-3'], array_column($this->started, 0));
        $this->assertSame('node-wf-project-1', $this->started[0][1]);
        $this->assertSame('project-1', $this->started[0][2]['projectId']);
    }

    public function testAnEventNoWorkflowWaitsForStartsNothing()
    {
        $dispatcher = new TriggerDispatcher($this->nodes([]), $this->engine());

        $this->assertSame(0, $dispatcher->dispatch(['eventName' => 'wiki.created', 'projectId' => 'project-1']));
        $this->assertSame([], $this->started);
    }

    public function testScopeDecodingAndCover()
    {
        $this->assertSame(['p1', 'p2'], WorkflowScope::decode('["p1","p2"]'));
        $this->assertSame([], WorkflowScope::decode(null));
        $this->assertSame([], WorkflowScope::decode('not json'));
        $this->assertFalse(WorkflowScope::covers([], null), 'there are no workflows outside projects');
        $this->assertFalse(WorkflowScope::covers([], 'p1'));
        $this->assertTrue(WorkflowScope::covers(['p1'], 'p1'));
        $this->assertFalse(WorkflowScope::covers(['p1'], null));
        $this->assertFalse(WorkflowScope::covers(['p1'], 'p2'));
    }

    public function testADueScheduleStartsItsWorkflowOnceForEachProjectOfItsScope()
    {
        $runner = new ScheduleTriggerRunner($this->nodes([
            'schedule' => [
                $this->node('wf-weekdays-9', ['project-1', 'project-2'], ['cron' => '0 9 * * 1-5']),
                $this->node('wf-hourly-15', ['project-1'], ['cron' => '15 * * * *']),
                $this->node('wf-no-scope', [], ['cron' => '0 9 * * 1-5']),
                $this->node('wf-broken', [], ['cron' => 'nonsense']),
                $this->node('wf-no-cron', [], []),
            ],
        ]), $this->engine());

        // Monday 2026-11-02 09:00:42 in UTC+5 is 04:00 UTC: neither is due
        $this->assertSame(0, $runner->run(new \DateTimeImmutable('2026-11-02 09:00:42', new \DateTimeZone('+05:00'))));

        $this->assertSame(2, $runner->run(new \DateTimeImmutable('2026-11-02 09:00:42', new \DateTimeZone('UTC'))));
        $this->assertSame(['wf-weekdays-9', 'wf-weekdays-9'], array_column($this->started, 0));
        $this->assertSame(['project-1', 'project-2'], array_column(array_column($this->started, 2), 'projectId'));
        $payload = $this->started[0][2];
        $this->assertSame('schedule', $payload['eventName']);
        $this->assertNotSame($payload['eventId'], $this->started[1][2]['eventId']);
        $this->assertSame(['cron' => '0 9 * * 1-5', 'firedAt' => '2026-11-02T09:00:00Z'], $payload['schedule']);
        $this->assertNotEmpty($payload['eventId']);
    }

    public function testARecordPayloadCarriesTheRecordTheChangesAndTheActor()
    {
        $envelope = [
            'eventId' => 'event-1',
            'eventName' => 'issue.assignee.changed',
            'projectId' => 'project-1',
            'actorId' => 'user-1',
            'meta' => ['actorName' => 'Ada'],
        ];
        $payload = (new TriggerPayload())->forRecord($envelope, 'Issue', [
            'id' => 'issue-1',
            'subject' => 'Ship it',
            'assignee' => 'user-2',
            'notAField' => 'x',
        ], [
            'assignee' => ['old' => 'user-3', 'new' => 'user-2'],
            'dateModified' => ['old' => 'a', 'new' => 'b'],
        ]);

        $this->assertSame('event-1', $payload['eventId']);
        $this->assertSame(['id' => 'user-1', 'name' => 'Ada'], $payload['actor']);
        $this->assertSame(['id' => 'issue-1', 'subject' => 'Ship it', 'assignee' => 'user-2'], $payload['issue']);
        $this->assertSame(['assignee' => ['before' => 'user-3', 'after' => 'user-2']], $payload['changes']);
    }

    /**
     * @param  string $workflowId
     * @param  array  $scope
     * @param  array  $config
     * @return array
     */
    private function node($workflowId, array $scope, array $config = [])
    {
        return ['workflowId' => $workflowId, 'nodeId' => 'node-' . $workflowId, 'config' => $config, 'scope' => $scope];
    }

    /**
     * @param  array $byKind kind => nodes
     * @return ActiveTriggerNodes
     */
    private function nodes(array $byKind)
    {
        return new class($byKind) extends ActiveTriggerNodes {
            private $byKind;

            public function __construct(array $byKind)
            {
                $this->byKind = $byKind;
            }

            public function ofKind($kind)
            {
                return isset($this->byKind[$kind]) ? $this->byKind[$kind] : [];
            }
        };
    }

    /**
     * @return WorkflowEngine
     */
    private function engine()
    {
        $started = &$this->started;

        return new class($started) implements WorkflowEngine {
            private $started;

            public function __construct(array &$started)
            {
                $this->started = &$started;
            }

            public function start($workflowId, $triggerNodeId, array $payload)
            {
                $this->started[] = [$workflowId, $triggerNodeId, $payload];
            }
        };
    }
}
