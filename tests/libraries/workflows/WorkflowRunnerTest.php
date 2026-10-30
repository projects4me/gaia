<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workflows;

use Gaia\Libraries\Workflows\ActionCatalog;
use Gaia\Libraries\Workflows\LoopGuard;
use Gaia\Libraries\Workflows\RunContext;
use Gaia\Libraries\Workflows\RunJournal;
use Gaia\Libraries\Workflows\WorkflowAction;
use Gaia\Libraries\Workflows\WorkflowChangeEvents;
use Gaia\Libraries\Workflows\WorkflowGraphReader;
use Gaia\Libraries\Workflows\WorkflowRunner;
use Gaia\Libraries\Workflows\WorkflowStepException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WorkflowRunner and LoopGuard: the path a run takes, the
 * run history it writes, failures, and the guard against loops.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests\Libraries\Workflows
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowRunnerTest extends TestCase
{
    /**
     * @var array The run history written: runs, steps, finished; and the
     *            journal calls that fail (start, step:<nodeId>, finish).
     */
    private $journal = ['runs' => [], 'steps' => [], 'finished' => [], 'fail' => []];

    /**
     * @var array[] The actions carried out: [kind, config, acting user].
     */
    private $carriedOut = [];

    /**
     * @var array[] The change events raised: [record, on, run].
     */
    private $raised = [];

    private const PAYLOAD = [
        'eventId' => 'event-1',
        'eventName' => 'issue.status.changed',
        'projectId' => 'project-1',
        'resource' => ['type' => 'issue', 'id' => 'issue-1'],
        'issue' => ['subject' => 'Ship it'],
        'changes' => ['status' => ['before' => 'New', 'after' => 'In Progress']],
    ];

    public function testARunFollowsThePortsToTheEndAndWritesEachStep()
    {
        $this->runner($this->graph([
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
        ]))->start('wf-1', 'trigger', self::PAYLOAD);

        $run = $this->journal['runs'][0];
        $this->assertSame(['wf-1', 4, 'trigger', 'issue.status.changed', 'project-1', null, 0], [
            $run['workflowId'], $run['workflowVersion'], $run['triggerNodeId'], $run['eventName'],
            $run['projectId'], $run['parentRunId'], $run['depth'],
        ]);
        $this->assertSame(self::PAYLOAD, $run['inputs']);

        $steps = $this->journal['steps'];
        $this->assertSame([1, 2, 3, 4], array_column($steps, 'sequence'));
        $this->assertSame(['trigger', 'filter', 'comment', 'end'], array_column($steps, 'nodeId'));
        $this->assertSame(['next', 'pass', 'next', null], array_column($steps, 'port'));
        $this->assertSame(['completed', 'completed', 'completed', 'completed'], array_column($steps, 'status'));
        $this->assertSame(['body' => 'Started: Ship it'], $steps[2]['inputs']);
        $this->assertSame(['entityId' => 'comment-1'], $steps[2]['outputs']);
        $this->assertSame([['run-1', 'completed', null]], $this->journal['finished']);

        $this->assertSame([['add_comment', ['body' => 'Started: {issue.subject}'], 'user-owner']], $this->carriedOut);
        $this->assertCount(1, $this->raised);
        $this->assertSame('create', $this->raised[0][1]);
        $this->assertSame(['id' => 'run-1', 'workflowIds' => ['wf-1'], 'depth' => 1], $this->raised[0][2]);
    }

    public function testAPortWithNoEdgeCompletesTheRun()
    {
        $this->runner($this->graph([
            $this->node('trigger', 'trigger', 'issue.status.changed'),
            $this->node('filter', 'condition', 'filter', ['conditions' => [
                ['field' => 'changes.status.after', 'operator' => 'equals', 'value' => 'Done'],
            ]]),
            $this->node('comment', 'action', 'add_comment', ['body' => 'x']),
        ], [['trigger', 'next', 'filter'], ['filter', 'pass', 'comment']]))->start('wf-1', 'trigger', self::PAYLOAD);

        $this->assertSame(['next', 'fail'], array_column($this->journal['steps'], 'port'));
        $this->assertSame([], $this->carriedOut);
        $this->assertSame([['run-1', 'completed', null]], $this->journal['finished']);
    }

    public function testAFailingStepFailsTheRun()
    {
        $this->runner($this->graph([
            $this->node('trigger', 'trigger', 'issue.status.changed'),
            $this->node('assign', 'action', 'assign', ['assigneeId' => 'user-stranger']),
            $this->node('end', 'action', 'end'),
        ], [['trigger', 'next', 'assign'], ['assign', 'next', 'end']]))->start('wf-1', 'trigger', self::PAYLOAD);

        $steps = $this->journal['steps'];
        $this->assertCount(2, $steps);
        $this->assertSame('failed', $steps[1]['status']);
        $this->assertNull($steps[1]['port']);
        $this->assertSame('user-stranger is not a member', $steps[1]['error']);
        $this->assertSame([['run-1', 'failed', 'user-stranger is not a member']], $this->journal['finished']);
    }

    public function testAnActorStepCannotRunYet()
    {
        $this->runner($this->graph([
            $this->node('trigger', 'trigger', 'issue.status.changed'),
            $this->node('agent', 'actor', 'run_agent', ['agentId' => 'agent-1']),
        ], [['trigger', 'next', 'agent']]))->start('wf-1', 'trigger', self::PAYLOAD);

        $this->assertSame([['run-1', 'failed', 'actor step run_agent cannot run yet.']], $this->journal['finished']);
    }

    public function testACycleEndsAfterTheStepLimit()
    {
        $this->runner($this->graph([
            $this->node('trigger', 'trigger', 'issue.status.changed'),
            $this->node('filter', 'condition', 'filter', ['conditions' => [
                ['field' => 'projectId', 'operator' => 'notEmpty'],
            ]]),
        ], [['trigger', 'next', 'filter'], ['filter', 'pass', 'filter']]))->start('wf-1', 'trigger', self::PAYLOAD);

        $this->assertCount(WorkflowRunner::MAX_STEPS, $this->journal['steps']);
        $this->assertSame('failed', $this->journal['finished'][0][1]);
    }

    public function testAnInactiveOrMissingWorkflowDoesNotRun()
    {
        $this->runner($this->graph([], [], 'Disabled'))->start('wf-1', 'trigger', self::PAYLOAD);
        $this->runner($this->graph([], [], null))->start('wf-1', 'trigger', self::PAYLOAD);

        $this->assertSame([], $this->journal['runs']);
    }

    public function testChangesAreMadeAsTheCreatorWhenTheWorkflowHasNoOwner()
    {
        $graph = $this->graph([
            $this->node('trigger', 'trigger', 'issue.status.changed'),
            $this->node('comment', 'action', 'add_comment', ['body' => 'x']),
        ], [['trigger', 'next', 'comment']], 'Active', null);

        $this->runner($graph)->start('wf-1', 'trigger', self::PAYLOAD);

        $this->assertSame('user-creator', $this->carriedOut[0][2]);
    }

    public function testAWorkflowTheJournalCannotStartIsSkipped()
    {
        $this->journal['fail'] = ['start'];

        $this->runner($this->commentGraph())->start('wf-1', 'trigger', self::PAYLOAD);

        $this->assertSame([], $this->carriedOut);
    }

    public function testChangeEventsGoOutWhenTheJournalCannotRecordTheStep()
    {
        $this->journal['fail'] = ['step:comment'];

        $this->runner($this->commentGraph())->start('wf-1', 'trigger', self::PAYLOAD);

        $this->assertCount(1, $this->raised);
        $this->assertSame([['run-1', 'failed', 'journal down']], $this->journal['finished']);
    }

    public function testARunTheJournalCannotFinishDoesNotStopTheCaller()
    {
        $this->journal['fail'] = ['finish'];

        $this->runner($this->commentGraph())->start('wf-1', 'trigger', self::PAYLOAD);

        $this->assertCount(2, $this->journal['steps']);
        $this->assertSame([], $this->journal['finished']);
    }

    public function testAWorkflowIsNotTriggeredByItsOwnRun()
    {
        $graph = $this->graph([$this->node('trigger', 'trigger', 'issue.status.changed')], []);
        $ownChange = self::PAYLOAD + [LoopGuard::KEY => ['id' => 'run-0', 'workflowIds' => ['wf-0', 'wf-1'], 'depth' => 2]];

        $this->runner($graph)->start('wf-1', 'trigger', $ownChange);
        $this->assertSame([], $this->journal['runs']);

        $this->runner($graph)->start('wf-2', 'trigger', $ownChange);
        $this->assertSame(['run-0', 2], [$this->journal['runs'][0]['parentRunId'], $this->journal['runs'][0]['depth']]);
    }

    public function testRunsNestOnlyToTheDepthLimit()
    {
        $guard = new LoopGuard();
        $deep = [LoopGuard::KEY => ['id' => 'run-0', 'workflowIds' => ['wf-0'], 'depth' => LoopGuard::MAX_DEPTH]];

        $this->assertNull($guard->refusal('wf-1', []));
        $this->assertNull($guard->refusal('wf-1', $deep));

        $deep[LoopGuard::KEY]['depth']++;
        $this->assertSame('runs nest deeper than ' . LoopGuard::MAX_DEPTH, $guard->refusal('wf-1', $deep));

        $context = new RunContext('run-1', ['id' => 'wf-1'], 'user-owner', $deep);
        $this->assertSame(
            ['id' => 'run-1', 'workflowIds' => ['wf-0', 'wf-1'], 'depth' => LoopGuard::MAX_DEPTH + 2],
            $guard->carried($context)
        );
    }

    /**
     * @param  WorkflowGraphReader $graph
     * @return WorkflowRunner
     */
    private function runner(WorkflowGraphReader $graph)
    {
        $journal = new class ($this->journal) extends RunJournal {
            private $written;

            public function __construct(array &$written)
            {
                $this->written = &$written;
            }

            public function start(array $run)
            {
                $this->failIf('start');
                $this->written['runs'][] = $run;
                return 'run-' . count($this->written['runs']);
            }

            public function step($runId, array $step)
            {
                $this->failIf('step:' . $step['nodeId']);
                $this->written['steps'][] = $step;
            }

            public function finish($runId, $status, $error = null)
            {
                $this->failIf('finish');
                $this->written['finished'][] = [$runId, $status, $error];
            }

            private function failIf($call)
            {
                if (in_array($call, $this->written['fail'], true)) {
                    throw new \RuntimeException('journal down');
                }
            }
        };

        $actions = new class ($this->carriedOut) extends ActionCatalog {
            private $carriedOut;

            public function __construct(array &$carriedOut)
            {
                $this->carriedOut = &$carriedOut;
            }

            public function action($kind)
            {
                $carriedOut = &$this->carriedOut;
                return new class ($kind, $carriedOut) extends WorkflowAction {
                    private $kind;
                    private $carriedOut;

                    public function __construct($kind, array &$carriedOut)
                    {
                        $this->kind = $kind;
                        $this->carriedOut = &$carriedOut;
                    }

                    public function run(array $config, RunContext $context)
                    {
                        if (isset($config['assigneeId'])) {
                            throw new WorkflowStepException("{$config['assigneeId']} is not a member");
                        }
                        $this->carriedOut[] = [$this->kind, $config, $context->actingUserId];
                        $context->changed(new \stdClass(), 'create');
                        return ['entityId' => 'comment-1'];
                    }
                };
            }
        };

        $events = new class ($this->raised) implements WorkflowChangeEvents {
            private $raised;

            public function __construct(array &$raised)
            {
                $this->raised = &$raised;
            }

            public function changed($record, $on, array $run)
            {
                $this->raised[] = [$record, $on, $run];
            }
        };

        return new WorkflowRunner($events, $graph, $journal, $actions);
    }

    /**
     * A trigger followed by one comment.
     *
     * @return WorkflowGraphReader
     */
    private function commentGraph()
    {
        return $this->graph([
            $this->node('trigger', 'trigger', 'issue.status.changed'),
            $this->node('comment', 'action', 'add_comment', ['body' => 'x']),
        ], [['trigger', 'next', 'comment']]);
    }

    /**
     * @param  array       $nodes
     * @param  array       $edges [from, port, to]
     * @param  string|null $status  null when the workflow is gone
     * @param  string|null $ownerId
     * @return WorkflowGraphReader
     */
    private function graph(array $nodes, array $edges, $status = 'Active', $ownerId = 'user-owner')
    {
        $workflow = $status === null ? null : [
            'id' => 'wf-1', 'name' => 'Triage', 'status' => $status, 'version' => 4,
            'ownerId' => $ownerId, 'createdUser' => 'user-creator',
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

    /**
     * @param  string $id
     * @param  string $category
     * @param  string $kind
     * @param  array  $config
     * @return array
     */
    private function node($id, $category, $kind, array $config = [])
    {
        return ['id' => $id, 'name' => $id, 'category' => $category, 'kind' => $kind, 'config' => $config];
    }
}
