<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workflows;

use Gaia\Libraries\Workflows\ActorCatalog;
use Gaia\Libraries\Workflows\ActorStepRefusal;
use Gaia\Libraries\Workflows\ActorStepReports;
use Gaia\Libraries\Workflows\ActorSteps;
use Gaia\Libraries\Workflows\ActorStepTimeouts;
use Gaia\Libraries\Workflows\ExpressionResolver;
use Gaia\Libraries\Workflows\ProjectMembers;
use Gaia\Libraries\Workflows\RunContext;
use Gaia\Libraries\Workflows\RunJournal;
use Gaia\Libraries\Workflows\UserReplies;
use Gaia\Libraries\Workflows\UserSelector;
use Gaia\Libraries\Workflows\WaitingStepResumer;
use Gaia\Libraries\Workflows\WorkflowRecords;
use Gaia\Libraries\Workflows\WorkflowStepException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the workflow actor steps: 'Run agent' and 'Ask a user'
 * (ActorCatalog), the runtime's claim and report (ActorStepReports), the
 * timeouts (ActorStepTimeouts) and the user's reply (UserReplies).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests\Libraries\Workflows
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowActorsTest extends TestCase
{
    /**
     * @var object The fake record store; `writes` lists [model, values, user].
     */
    private $records;

    /**
     * @var object The fake actor steps; `rows` by id.
     */
    private $steps;

    /**
     * @var array[] The answers handed to the engine: [stepId, port, outputs].
     */
    private $resumed = [];

    /**
     * @var ActorCatalog
     */
    private $actors;

    protected function setUp(): void
    {
        $this->records = $this->records();
        $this->steps = $this->steps();
        $users = new UserSelector(new class extends ProjectMembers {
            public function isMember($projectId, $userId)
            {
                return $projectId === 'project-1' && in_array($userId, ['user-owner', 'user-ada'], true);
            }

            public function firstWithRole($projectId, $role)
            {
                return null;
            }

            public function sponsorOf($userId)
            {
                return null;
            }
        });
        $this->actors = new ActorCatalog(
            $this->records,
            $users,
            new ExpressionResolver($users->functions()),
            $this->steps
        );
    }

    public function testRunAgentRecordsAPendingStepAndAsksForTheAgentsRuntime()
    {
        $before = time();
        $context = $this->context();
        $outputs = $this->actors->actor('run_agent')->run([
            'agentId' => 'agent-1',
            'prompt' => 'Work on {issue.subject}',
            'session' => 'resume',
            'model' => 'opus',
            'claimTimeout' => '10',
            'timeout' => '60',
        ], $context);

        $step = $this->steps->rows['step-1'];
        $this->assertSame(
            ['step-1', 'run-1', 'wf-1', 'node-1', 'run_agent', 'user-agent', 'agent-1', 'project-1', 'issue', 'issue-1'],
            [
                $step['id'], $step['runId'], $step['workflowId'], $step['nodeId'], $step['kind'], $step['actorUserId'],
                $step['agentId'], $step['projectId'], $step['entityType'], $step['entityId'],
            ]
        );
        $this->assertSame(['Work on Ship it', 'resume', 'opus', 30], [$step['prompt'], $step['session'], $step['model'], $step['maxTurns']]);
        $this->assertSame('pending', $step['status']);
        $this->assertEqualsWithDelta($before + 600, strtotime($step['claimBy'] . ' UTC'), 5);
        $this->assertEqualsWithDelta($before + 3600, strtotime($step['finishBy'] . ' UTC'), 5);
        $this->assertSame([$step], array_map(function ($request) {
            return $request + ['status' => 'pending'];
        }, $context->takeAgentRequests()));
        $this->assertSame(['agentId', 'actorUserId', 'claimBy', 'finishBy'], array_keys($outputs));
    }

    public function testRunAgentDefaultsToANewSessionTheAgentsTurnsAndTheDefaultDeadlines()
    {
        $before = time();
        $this->actors->actor('run_agent')->run(['agentId' => 'agent-1', 'maxTurns' => '12'], $this->context(null));

        $step = $this->steps->rows['step-1'];
        $this->assertSame(['new', null, 12, null, null, ''], [
            $step['session'], $step['model'], $step['maxTurns'], $step['entityType'], $step['entityId'], $step['prompt'],
        ]);
        $this->assertEqualsWithDelta($before + 15 * 60, strtotime($step['claimBy'] . ' UTC'), 5);
        $this->assertEqualsWithDelta($before + 240 * 60, strtotime($step['finishBy'] . ' UTC'), 5);
    }

    /**
     * @dataProvider badRunAgentConfigs
     */
    public function testRunAgentRefusesABadConfig(array $config, $message)
    {
        $this->expectException(WorkflowStepException::class);
        $this->expectExceptionMessage($message);

        $this->actors->actor('run_agent')->run($config + ['agentId' => 'agent-1'], $this->context());
    }

    /**
     * @return array
     */
    public function badRunAgentConfigs()
    {
        return [
            'gone agent' => [['agentId' => 'agent-gone'], 'Agent agent-gone no longer exists.'],
            'session' => [['session' => 'fork'], 'config.session must be new or resume.'],
            'timeout' => [['timeout' => 'soon'], 'config.timeout must be a whole number above zero.'],
            'claim after finish' => [['claimTimeout' => '90', 'timeout' => '60'], 'config.claimTimeout must not be longer'],
            'turns' => [['maxTurns' => '-3'], 'config.maxTurns must be a whole number above zero.'],
        ];
    }

    public function testAskUserPostsTheQuestionAndWaitsForThatUser()
    {
        $context = $this->context('comment', 'comment-1');
        $outputs = $this->actors->actor('ask_user')->run(
            ['userId' => 'user-ada', 'question' => 'Ready to ship {issue.subject}?'],
            $context
        );

        $this->assertSame(
            [['Comment', ['comment' => 'Ready to ship Ship it?', 'relatedTo' => 'issue', 'relatedId' => 'issue-9'], 'user-owner']],
            $this->records->writes
        );
        $step = $this->steps->rows['step-1'];
        $this->assertSame(['ask_user', 'user-ada', 'issue', 'issue-9', 'Ready to ship Ship it?', 'pending'], [
            $step['kind'], $step['actorUserId'], $step['entityType'], $step['entityId'], $step['prompt'], $step['status'],
        ]);
        $this->assertSame(['userId' => 'user-ada', 'questionId' => 'comment-new'], $outputs);
        $this->assertSame([], $context->takeAgentRequests());
    }

    public function testAskUserNeedsAProjectMemberAndARecord()
    {
        try {
            $this->actors->actor('ask_user')->run(['userId' => 'user-stranger', 'question' => 'OK?'], $this->context());
            $this->fail('A stranger was asked.');
        } catch (WorkflowStepException $e) {
            $this->assertStringContainsString('not a member of the project', $e->getMessage());
        }

        $this->expectExceptionMessage('The event is not about a record.');
        $this->actors->actor('ask_user')->run(['userId' => 'user-ada', 'question' => 'OK?'], $this->context(null));
    }

    public function testTheAgentClaimsItsStepOnceThenReportsAndTheRunGoesOn()
    {
        $this->steps->rows['step-1'] = $this->agentStep('pending');
        $reports = new ActorStepReports($this->steps, $this->resumer());

        $reports->update('step-1', 'user-agent', ['id' => 'step-1', 'status' => 'claimed']);
        $this->assertSame('claimed', $this->steps->rows['step-1']['status']);
        $this->assertNotNull($this->steps->rows['step-1']['claimedAt']);
        $this->assertRefused(409, function () use ($reports) {
            $reports->update('step-1', 'user-agent', ['status' => 'claimed']);
        });

        $reports->update('step-1', 'user-agent', ['outcome' => 'needs_input', 'comment' => 'Which API?']);
        $this->assertSame(['finished', 'needs_input', 'Which API?'], [
            $this->steps->rows['step-1']['status'], $this->steps->rows['step-1']['outcome'], $this->steps->rows['step-1']['comment'],
        ]);
        $this->assertSame([['step-1', 'needs_input', ['outcome' => 'needs_input', 'comment' => 'Which API?']]], $this->resumed);
        $this->assertRefused(409, function () use ($reports) {
            $reports->update('step-1', 'user-agent', ['outcome' => 'done']);
        });
        $this->assertCount(1, $this->resumed);
    }

    public function testAReportNeedsAClaimFirst()
    {
        $this->steps->rows['step-1'] = $this->agentStep('pending');

        $this->assertRefused(409, function () {
            (new ActorStepReports($this->steps, $this->resumer()))->update('step-1', 'user-agent', ['outcome' => 'done']);
        });
        $this->assertSame([], $this->resumed);
    }

    /**
     * @dataProvider refusedUpdates
     */
    public function testUpdatesAreRefused($userId, array $update, $code, $stepId = 'step-1')
    {
        $this->steps->rows['step-1'] = $this->agentStep('claimed');
        $this->steps->rows['step-ask'] = ['id' => 'step-ask', 'kind' => 'ask_user', 'actorUserId' => 'user-agent', 'status' => 'pending'];

        $this->assertRefused($code, function () use ($stepId, $userId, $update) {
            (new ActorStepReports($this->steps, $this->resumer()))->update($stepId, $userId, $update);
        });
        $this->assertSame('claimed', $this->steps->rows['step-1']['status']);
    }

    /**
     * @return array
     */
    public function refusedUpdates()
    {
        return [
            'another user' => ['user-ada', ['outcome' => 'done'], 403],
            'unknown step' => ['user-agent', ['outcome' => 'done'], 404, 'step-gone'],
            'ask a user step' => ['user-agent', ['outcome' => 'done'], 422, 'step-ask'],
            'unknown outcome' => ['user-agent', ['outcome' => 'shipped'], 422],
            'other field' => ['user-agent', ['outcome' => 'done', 'prompt' => 'x'], 422],
            'nothing to do' => ['user-agent', ['status' => 'finished'], 422],
            'report with another status' => ['user-agent', ['outcome' => 'done', 'status' => 'claimed'], 422],
        ];
    }

    public function testStepsPastTheirDeadlineTakeTheFailedOutput()
    {
        $this->steps->rows['step-1'] = $this->agentStep('pending');
        $this->steps->rows['step-2'] = ['id' => 'step-2', 'claimBy' => null, 'finishBy' => '2026-10-30 10:00:00'] + $this->agentStep('claimed');
        $this->steps->rows['step-3'] = ['id' => 'step-3'] + $this->agentStep('finished');
        $this->steps->overdue = ['step-1', 'step-2', 'step-3'];

        $timedOut = (new ActorStepTimeouts($this->steps, $this->resumer()))->run(new \DateTimeImmutable('2026-10-30 12:00:00 UTC'));

        $this->assertSame(2, $timedOut);
        $this->assertSame(['timed_out', 'failed'], [$this->steps->rows['step-1']['status'], $this->steps->rows['step-1']['outcome']]);
        $this->assertSame('finished', $this->steps->rows['step-3']['status']);
        $this->assertSame(['step-1', 'step-2'], array_column($this->resumed, 0));
        $this->assertSame(['failed', 'failed'], array_column($this->resumed, 1));
        $this->assertSame(
            "The agent's runtime did not claim the step by 2026-10-30 09:00:00 UTC.",
            $this->resumed[0][2]['comment']
        );
        $this->assertSame('The agent did not report by 2026-10-30 10:00:00 UTC.', $this->resumed[1][2]['comment']);
    }

    public function testAnswersThatNeverReachedTheEngineAreHandedOverAgain()
    {
        $this->steps->rows['step-1'] = ['outcome' => 'done', 'comment' => 'PR opened'] + $this->agentStep('finished');
        $this->steps->rows['step-ask'] = ['status' => 'finished', 'outcome' => 'replied', 'comment' => 'Yes'] + $this->askStep();
        $this->steps->rows['step-3'] = ['id' => 'step-3'] + $this->agentStep('claimed');
        $journal = new class extends RunJournal {
            public function waitingActorStepIds()
            {
                return ['step-1', 'step-ask', 'step-3'];
            }
        };

        $retried = (new ActorStepTimeouts($this->steps, $this->resumer(), $journal))
            ->retry(new \DateTimeImmutable('2026-10-30 12:00:00 UTC'));

        $this->assertSame(2, $retried);
        $this->assertSame([
            ['step-1', 'done', ['outcome' => 'done', 'comment' => 'PR opened']],
            ['step-ask', 'replied', ['reply' => 'Yes']],
        ], $this->resumed);
        $this->assertSame('2026-10-30 11:59:00', $this->steps->answeredBefore);
    }

    public function testTheAskedUsersCommentAnswersTheStep()
    {
        $this->steps->rows['step-ask'] = $this->askStep();
        $replies = new UserReplies($this->steps, $this->resumer());

        $this->assertSame(0, $replies->commented('issue', 'issue-9', 'user-owner', 'comment-2', 'Me neither'));
        $this->assertSame(0, $replies->commented('wiki', 'issue-9', 'user-ada', 'comment-3', 'Wrong record'));
        $this->assertSame(1, $replies->commented('issue', 'issue-9', 'user-ada', 'comment-4', 'Yes, ship it'));
        $this->assertSame(0, $replies->commented('issue', 'issue-9', 'user-ada', 'comment-5', 'Again'));

        $this->assertSame([['step-ask', 'replied', ['userId' => 'user-ada', 'replyId' => 'comment-4', 'reply' => 'Yes, ship it']]], $this->resumed);
        $this->assertSame(['finished', 'replied'], [$this->steps->rows['step-ask']['status'], $this->steps->rows['step-ask']['outcome']]);
    }

    public function testReassigningTheRecordAnswersTheStep()
    {
        $this->steps->rows['step-ask'] = $this->askStep();
        $replies = new UserReplies($this->steps, $this->resumer());

        $this->assertSame(1, $replies->reassigned('issue', 'issue-9', 'user-owner'));

        $this->assertSame([['step-ask', 'reassigned', ['assigneeId' => 'user-owner']]], $this->resumed);
    }

    /**
     * @param  int      $code
     * @param  callable $update
     * @return void
     */
    private function assertRefused($code, callable $update)
    {
        try {
            $update();
            $this->fail("The update was not refused with {$code}.");
        } catch (ActorStepRefusal $refusal) {
            $this->assertSame($code, $refusal->getCode(), $refusal->getMessage());
        }
    }

    /**
     * @param  string $status
     * @return array
     */
    private function agentStep($status)
    {
        return [
            'id' => 'step-1', 'kind' => 'run_agent', 'actorUserId' => 'user-agent', 'status' => $status,
            'claimBy' => '2026-10-30 09:00:00', 'finishBy' => '2026-10-30 13:00:00',
            'outcome' => null, 'comment' => null, 'claimedAt' => null, 'finishedAt' => null,
        ];
    }

    /**
     * @return array
     */
    private function askStep()
    {
        return [
            'id' => 'step-ask', 'kind' => 'ask_user', 'actorUserId' => 'user-ada', 'status' => 'pending',
            'entityType' => 'issue', 'entityId' => 'issue-9',
        ];
    }

    /**
     * @param  string|null $type
     * @param  string|null $id
     * @return RunContext
     */
    private function context($type = 'issue', $id = 'issue-1')
    {
        $payload = ['projectId' => 'project-1', 'issue' => ['subject' => 'Ship it']];
        if ($type !== null) {
            $payload['resource'] = ['type' => $type, 'id' => $id];
        }
        $context = new RunContext('run-1', ['id' => 'wf-1', 'name' => 'Triage', 'version' => 1], 'user-owner', $payload);
        $context->step = ['id' => 'step-1', 'nodeId' => 'node-1'];

        return $context;
    }

    /**
     * @return WaitingStepResumer
     */
    private function resumer()
    {
        $resumed = &$this->resumed;

        return new class ($resumed) implements WaitingStepResumer {
            private $resumed;

            public function __construct(array &$resumed)
            {
                $this->resumed = &$resumed;
            }

            public function resume($stepId, $port, array $outputs)
            {
                $this->resumed[] = [$stepId, $port, $outputs];
            }
        };
    }

    /**
     * Actor steps in memory; `overdue` names the rows overdue() returns.
     *
     * @return ActorSteps
     */
    private function steps()
    {
        return new class extends ActorSteps {
            public $rows = [];
            public $overdue = [];
            public $answeredBefore;

            public function create(array $step)
            {
                $this->rows[$step['id']] = $step + ['status' => 'pending'];
            }

            public function find($id)
            {
                return isset($this->rows[$id]) ? $this->rows[$id] : null;
            }

            public function transition($id, array $from, array $values)
            {
                if (!isset($this->rows[$id]) || !in_array($this->rows[$id]['status'], $from, true)) {
                    return false;
                }
                $this->rows[$id] = $values + $this->rows[$id];

                return true;
            }

            public function overdue($now)
            {
                return array_map(function ($id) {
                    return $this->rows[$id];
                }, $this->overdue);
            }

            public function waitingOn($entityType, $entityId)
            {
                return array_values(array_filter($this->rows, function ($row) use ($entityType, $entityId) {
                    return $row['kind'] === 'ask_user' && $row['status'] === 'pending'
                        && $row['entityType'] === $entityType && $row['entityId'] === $entityId;
                }));
            }

            public function answered(array $ids, $before)
            {
                $this->answeredBefore = $before;
                return array_values(array_filter($this->rows, function ($row) use ($ids) {
                    return in_array($row['id'], $ids, true) && in_array($row['status'], ['finished', 'timed_out'], true);
                }));
            }
        };
    }

    /**
     * A record store with agent-1 (user-agent, 30 turns), issue-1 and
     * comment-1 (on issue-9).
     *
     * @return WorkflowRecords
     */
    private function records()
    {
        return new class extends WorkflowRecords {
            public $writes = [];

            public function find($model, $id)
            {
                $rows = [
                    'Agent' => ['agent-1' => ['id' => 'agent-1', 'userId' => 'user-agent', 'maxTurns' => 30]],
                    'Issue' => ['issue-1' => ['id' => 'issue-1', 'projectId' => 'project-1']],
                    'Comment' => ['comment-1' => ['id' => 'comment-1', 'relatedTo' => 'issue', 'relatedId' => 'issue-9']],
                ];

                return isset($rows[$model][$id]) ? $this->record($model, $rows[$model][$id]) : null;
            }

            public function create($model, array $values, $userId)
            {
                $this->writes[] = [$model, $values, $userId];
                return $this->record($model, ['id' => strtolower($model) . '-new'] + $values);
            }

            public function modelName($record)
            {
                return $record->model;
            }

            private function record($model, array $values)
            {
                return new class ($model, $values) {
                    public $model;
                    private $values;

                    public function __construct($model, array $values)
                    {
                        $this->model = $model;
                        $this->values = $values;
                    }

                    public function readAttribute($field)
                    {
                        return isset($this->values[$field]) ? $this->values[$field] : null;
                    }
                };
            }
        };
    }
}
