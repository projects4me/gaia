<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workflows;

use Gaia\Libraries\Workflows\ActionCatalog;
use Gaia\Libraries\Workflows\ExpressionResolver;
use Gaia\Libraries\Workflows\ProjectMembers;
use Gaia\Libraries\Workflows\RunContext;
use Gaia\Libraries\Workflows\UserSelector;
use Gaia\Libraries\Workflows\WorkflowRecords;
use Gaia\Libraries\Workflows\WorkflowStepException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the workflow actions: what each writes, as which user,
 * and what it refuses.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests\Libraries\Workflows
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowActionsTest extends TestCase
{
    /**
     * @var object The fake record store; `writes` lists [create|update, model, values, user].
     */
    private $records;

    /**
     * @var ActionCatalog
     */
    private $actions;

    protected function setUp(): void
    {
        $this->records = $this->records();
        $users = new UserSelector(new class extends ProjectMembers {
            public function isMember($projectId, $userId)
            {
                return $projectId === 'project-1' && in_array($userId, ['user-owner', 'user-ada', 'user-dev'], true);
            }

            public function firstWithRole($projectId, $role)
            {
                return $role === 'Developer' ? 'user-dev' : null;
            }

            public function sponsorOf($userId)
            {
                return null;
            }
        });
        $this->actions = new ActionCatalog($this->records, $users, new ExpressionResolver($users->functions()));
    }

    public function testAssignAssignsTheIssueAsTheActingUser()
    {
        $context = $this->context('issue', 'issue-1');

        $outputs = $this->actions->action('assign')->run(['assigneeId' => '{role:Developer}'], $context);

        $this->assertSame(['update', 'Issue', ['assignee' => 'user-dev'], 'user-owner'], $this->records->writes[0]);
        $this->assertSame(['entityType' => 'Issue', 'entityId' => 'issue-1', 'changed' => ['assignee' => 'user-dev']], $outputs);
        $this->assertSame('update', $context->takeChanges()[0][1]);
    }

    public function testAssignRefusesSomeoneOutsideTheProject()
    {
        $this->expectException(WorkflowStepException::class);
        $this->expectExceptionMessage('not a member of the project');

        $this->actions->action('assign')->run(['assigneeId' => 'user-stranger'], $this->context('issue', 'issue-1'));
    }

    public function testSetFieldSetsAFieldButNotAProtectedOrUnknownOne()
    {
        $this->actions->action('set_field')->run(['field' => 'priority', 'value' => 'high'], $this->context('issue', 'issue-1'));
        $this->assertSame(['update', 'Issue', ['priority' => 'high'], 'user-owner'], $this->records->writes[0]);

        foreach (['projectId' => 'cannot set Issue.projectId', 'nope' => 'has no field nope'] as $field => $message) {
            try {
                $this->actions->action('set_field')->run(['field' => $field, 'value' => 'x'], $this->context('issue', 'issue-1'));
                $this->fail("{$field} was set");
            } catch (WorkflowStepException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    public function testSetFieldOnlyChangesModelsThatRaiseTriggerEvents()
    {
        $this->expectExceptionMessage('A workflow cannot change a User.');
        $this->actions->action('set_field')->run(['field' => 'subject', 'value' => 'x'], $this->context('user', 'user-1'));
    }

    public function testSetStatusUsesTheProjectsStatus()
    {
        $this->actions->action('set_status')->run(['status' => 'In Review'], $this->context('issue', 'issue-1'));
        $this->assertSame(['update', 'Issue', ['statusId' => 'status-review', 'status' => 'In Review'], 'user-owner'], $this->records->writes[0]);

        $this->expectExceptionMessage('The project has no issue status Gone.');
        $this->actions->action('set_status')->run(['status' => 'Gone'], $this->context('issue', 'issue-1'));
    }

    public function testACommentBelongsToTheChosenMember()
    {
        $this->actions->action('add_comment')->run(
            ['body' => 'Picked up {issue.subject}', 'ownerId' => 'user-ada'],
            $this->context('issue', 'issue-1')
        );
        $this->actions->action('add_comment')->run(['body' => 'Thanks'], $this->context('comment', 'comment-1'));

        $this->assertSame(
            ['create', 'Comment', ['comment' => 'Picked up Ship it', 'relatedTo' => 'issue', 'relatedId' => 'issue-1'], 'user-ada'],
            $this->records->writes[0]
        );
        $this->assertSame(
            ['create', 'Comment', ['comment' => 'Thanks', 'relatedTo' => 'issue', 'relatedId' => 'issue-9'], 'user-owner'],
            $this->records->writes[1],
            'a comment on a comment goes on the commented record'
        );
    }

    public function testACreatedIssueBelongsToAndIsAssignedToTheChosenMember()
    {
        $context = $this->context('issue', 'issue-1');

        $outputs = $this->actions->action('create_issue')->run(['subject' => 'Follow up {issue.subject}', 'ownerId' => 'user-ada'], $context);

        $this->assertSame(['create', 'Issue', [
            'subject' => 'Follow up Ship it',
            'description' => '',
            'typeId' => 'type-task',
            'priority' => 'medium',
            'projectId' => 'project-1',
            'projectShortcode' => 'agaia',
            'owner' => 'user-ada',
            'reportedUser' => 'user-ada',
            'assignee' => 'user-ada',
        ], 'user-ada'], $this->records->writes[0]);
        $this->assertSame('user-ada', $outputs['createdUser']);
        $this->assertSame('create', $context->takeChanges()[0][1]);

        $this->actions->action('create_issue')->run(
            ['subject' => 'Review', 'typeId' => 'type-bug', 'assigneeId' => '{role:Developer}'],
            $context
        );
        $this->assertSame(
            ['type-bug', 'user-owner', 'user-dev'],
            [$this->records->writes[1][2]['typeId'], $this->records->writes[1][2]['owner'], $this->records->writes[1][2]['assignee']]
        );
    }

    public function testCreatingAnIssueOutsideAnIssueEventNeedsAType()
    {
        $this->expectExceptionMessage('config.typeId is required');

        $this->actions->action('create_issue')->run(['subject' => 'Weekly review'], $this->context(null, null));
    }

    public function testACreatedWikiPageBelongsToTheChosenMember()
    {
        $this->actions->action('create_wiki')->run(['title' => 'Notes {issue.subject}', 'ownerId' => '{role:Developer}'], $this->context('issue', 'issue-1'));

        $this->assertSame(['create', 'Wiki', [
            'name' => 'Notes Ship it',
            'markUp' => '',
            'projectId' => 'project-1',
            'status' => 'published',
            'locked' => 0,
            'upvotes' => 0,
        ], 'user-dev'], $this->records->writes[0]);
    }

    public function testNotifyAndCallToolCannotRunYet()
    {
        foreach (['notify', 'call_tool'] as $kind) {
            try {
                $this->actions->action($kind);
                $this->fail("{$kind} ran");
            } catch (WorkflowStepException $e) {
                $this->assertSame("Action {$kind} cannot run yet.", $e->getMessage());
            }
        }
    }

    public function testAnActionOnARecordThatIsGoneFails()
    {
        $this->expectExceptionMessage('Issue issue-gone no longer exists.');

        $this->actions->action('assign')->run(['assigneeId' => 'user-ada'], $this->context('issue', 'issue-gone'));
    }

    /**
     * @param  string|null $type
     * @param  string|null $id
     * @return RunContext
     */
    private function context($type, $id)
    {
        $payload = ['projectId' => 'project-1', 'issue' => ['subject' => 'Ship it']];
        if ($type !== null) {
            $payload['resource'] = ['type' => $type, 'id' => $id];
        }

        return new RunContext('run-1', ['id' => 'wf-1', 'name' => 'Triage', 'version' => 1], 'user-owner', $payload);
    }

    /**
     * A record store with issue-1 (a task in project-1) and comment-1 (on issue-9).
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
                    'Issue' => ['issue-1' => ['id' => 'issue-1', 'projectId' => 'project-1', 'typeId' => 'type-task']],
                    'Comment' => ['comment-1' => ['id' => 'comment-1', 'relatedTo' => 'issue', 'relatedId' => 'issue-9']],
                    'User' => ['user-1' => ['id' => 'user-1']],
                ];

                return isset($rows[$model][$id]) ? $this->record($model, $rows[$model][$id]) : null;
            }

            public function create($model, array $values, $userId)
            {
                $this->writes[] = ['create', $model, $values, $userId];
                return $this->record($model, ['id' => strtolower($model) . '-new'] + $values);
            }

            public function update($record, array $values, $userId)
            {
                $this->writes[] = ['update', $record->model, $values, $userId];
                return $record;
            }

            public function modelName($record)
            {
                return $record->model;
            }

            public function fields($model)
            {
                return ['assignee' => 'string', 'priority' => 'string', 'projectId' => 'string', 'subject' => 'string'];
            }

            public function issueStatus($projectId, $status)
            {
                return $projectId === 'project-1' && $status === 'In Review' ? ['id' => 'status-review', 'name' => 'In Review'] : null;
            }

            public function projectShortCode($projectId)
            {
                return $projectId === 'project-1' ? 'agaia' : null;
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
