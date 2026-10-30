<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workflows;

use Gaia\Libraries\Workflows\TestRunRefusal;
use Gaia\Libraries\Workflows\WorkflowGraphReader;
use Gaia\Libraries\Workflows\WorkflowTestRun;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WorkflowTestRun: the path a simulated run takes and the
 * resolved inputs it reports, without anything being carried out.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests\Libraries\Workflows
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowTestRunTest extends TestCase
{
    private const PAYLOAD = [
        'eventName' => 'issue.status.changed',
        'projectId' => 'project-1',
        'issue' => ['subject' => 'Ship it'],
        'changes' => ['status' => ['before' => 'New', 'after' => 'In Progress']],
    ];

    public function testTheRunFollowsTheMatchingPortAndResolvesTheInputs()
    {
        $run = (new WorkflowTestRun($this->graph([
            $this->node('trigger', 'trigger', 'issue.status.changed'),
            $this->node('filter', 'condition', 'filter', ['conditions' => [
                ['field' => 'changes.status.after', 'operator' => 'equals', 'value' => 'In Progress'],
            ]]),
            $this->node('comment', 'action', 'add_comment', ['body' => 'Started: {issue.subject}']),
            $this->node('end', 'action', 'end'),
        ], [
            ['trigger', 'next', 'filter'],
            ['filter', 'pass', 'comment'],
            ['comment', 'next', 'end'],
        ])))->run('wf-1', 'trigger', self::PAYLOAD);

        $this->assertSame('completed', $run['status']);
        $this->assertSame(['trigger', 'filter', 'comment', 'end'], $run['path']);
        $this->assertSame(['next', 'pass', 'next', null], array_column($run['steps'], 'port'));
        $this->assertSame(
            ['completed', 'completed', 'simulated', 'simulated'],
            array_column($run['steps'], 'status')
        );
        $this->assertSame(['body' => 'Started: Ship it'], $run['steps'][2]['inputs']);
        $this->assertSame(self::PAYLOAD, $run['inputs']);
        $this->assertSame(4, $run['workflowVersion']);
    }

    public function testAPortWithNoEdgeEndsThePath()
    {
        $run = (new WorkflowTestRun($this->graph([
            $this->node('trigger', 'trigger', 'issue.status.changed'),
            $this->node('filter', 'condition', 'filter', ['conditions' => [
                ['field' => 'changes.status.after', 'operator' => 'equals', 'value' => 'Done'],
            ]]),
            $this->node('comment', 'action', 'add_comment', ['body' => 'x']),
        ], [['trigger', 'next', 'filter'], ['filter', 'pass', 'comment']])))->run('wf-1', 'trigger', self::PAYLOAD);

        $this->assertSame('completed', $run['status']);
        $this->assertSame(['trigger', 'filter'], $run['path']);
        $this->assertSame('fail', $run['steps'][1]['port']);
    }

    public function testAnActorStepGoesOnByTheNamedPortOrItsFirst()
    {
        $nodes = [
            $this->node('trigger', 'trigger', 'issue.status.changed'),
            $this->node('ask', 'actor', 'ask_user', ['userId' => 'user-1', 'message' => 'ok?']),
            $this->node('a', 'action', 'end'),
            $this->node('b', 'action', 'end'),
        ];
        $edges = [['trigger', 'next', 'ask'], ['ask', 'replied', 'a'], ['ask', 'reassigned', 'b']];
        $test = new WorkflowTestRun($this->graph($nodes, $edges));

        $this->assertSame(['trigger', 'ask', 'a'], $test->run('wf-1', 'trigger', self::PAYLOAD)['path']);
        $named = $test->run('wf-1', 'trigger', self::PAYLOAD, ['ask' => 'reassigned']);
        $this->assertSame(['trigger', 'ask', 'b'], $named['path']);
        $this->assertSame('simulated', $named['steps'][1]['status']);

        $bad = $test->run('wf-1', 'trigger', self::PAYLOAD, ['ask' => 'nope']);
        $this->assertSame('failed', $bad['status']);
        $this->assertSame('The step has no port nope.', $bad['error']);
    }

    public function testTheTriggerIsTheOnlyOneOrTheOneNamed()
    {
        $test = new WorkflowTestRun($this->graph([
            $this->node('t1', 'trigger', 'issue.created'),
            $this->node('t2', 'trigger', 'comment.added'),
        ], []));

        $this->assertSame('t2', $test->trigger('wf-1', 't2')['id']);
        $this->expectException(TestRunRefusal::class);
        $test->trigger('wf-1');
    }

    public function testAMissingWorkflowIsRefused()
    {
        $this->expectException(TestRunRefusal::class);
        $this->expectExceptionCode(404);
        (new WorkflowTestRun($this->graph([], [], null)))->trigger('wf-1');
    }

    private function graph(array $nodes, array $edges, $status = 'Draft')
    {
        $workflow = $status === null ? null : [
            'id' => 'wf-1', 'name' => 'Triage', 'status' => $status, 'version' => 4,
            'ownerId' => 'user-owner', 'createdUser' => 'user-creator',
        ];
        $edges = array_map(function ($edge) {
            return ['id' => implode('-', $edge), 'fromNodeId' => $edge[0], 'fromPort' => $edge[1], 'toNodeId' => $edge[2]];
        }, $edges);

        return new class ($workflow, $nodes, $edges) extends WorkflowGraphReader {
            private $workflow;
            private $nodes;
            private $edges;

            public function __construct($workflow, array $nodes, array $edges)
            {
                $this->workflow = $workflow;
                $this->nodes = $nodes;
                $this->edges = $edges;
            }

            public function workflow($workflowId)
            {
                return $this->workflow === null ? null : ['id' => $workflowId] + $this->workflow;
            }

            public function nodes($workflowId)
            {
                return $this->nodes;
            }

            public function edges($workflowId)
            {
                return $this->edges;
            }
        };
    }

    private function node($id, $category, $kind, array $config = [])
    {
        return ['id' => $id, 'name' => $id, 'category' => $category, 'kind' => $kind, 'config' => $config];
    }
}
