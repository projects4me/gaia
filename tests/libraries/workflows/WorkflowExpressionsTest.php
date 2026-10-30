<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workflows;

use Gaia\Libraries\Workflows\ConditionEvaluator;
use Gaia\Libraries\Workflows\ExpressionResolver;
use Gaia\Libraries\Workflows\ProjectMembers;
use Gaia\Libraries\Workflows\UserSelector;
use Gaia\Libraries\Workflows\WorkflowStepException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the workflow expressions, Filter and Switch conditions and
 * the choice of project members.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests\Libraries\Workflows
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowExpressionsTest extends TestCase
{
    private const VALUES = [
        'projectId' => 'project-1',
        'actor' => ['id' => 'user-ada'],
        'issue' => ['number' => 42, 'subject' => 'Ship it', 'assignee' => 'user-agent', 'tags' => ['a', 'b']],
        'changes' => ['status' => ['before' => 'New', 'after' => 'In Progress']],
    ];

    public function testAnExpressionKeepsItsValueAndATemplateIsFilledIn()
    {
        $expressions = new ExpressionResolver();

        $this->assertSame(42, $expressions->resolve('{issue.number}', self::VALUES));
        $this->assertSame(42, $expressions->resolve(' { issue.number } ', self::VALUES));
        $this->assertSame('#42: Ship it ()', $expressions->resolve('#{issue.number}: {issue.subject} ({issue.missing})', self::VALUES));
        $this->assertSame('tags ["a","b"]', $expressions->resolve('tags {issue.tags}', self::VALUES));
        $this->assertNull($expressions->resolve('{issue.subject.deeper}', self::VALUES));
        $this->assertSame('plain', $expressions->resolve('plain', self::VALUES));
        $this->assertSame('function () { return 1; } #42', $expressions->resolve('function () { return 1; } #{issue.number}', self::VALUES));
        $this->assertSame(['a'], $expressions->resolve(['a'], self::VALUES));
    }

    public function testAnUnknownFunctionFails()
    {
        $this->expectException(WorkflowStepException::class);
        $this->expectExceptionMessage("unknown function 'nope'");

        (new ExpressionResolver())->resolve('{nope:x}', self::VALUES);
    }

    /**
     * @dataProvider conditions
     */
    public function testConditions(array $condition, $holds)
    {
        $evaluator = new ConditionEvaluator(new ExpressionResolver());

        $this->assertSame($holds, $evaluator->all([$condition], self::VALUES));
    }

    public function conditions()
    {
        return [
            'equals' => [['field' => 'issue.number', 'operator' => 'equals', 'value' => '42'], true],
            'equals an expression' => [['field' => 'issue.assignee', 'operator' => 'equals', 'value' => '{actor.id}'], false],
            'notEquals' => [['field' => 'changes.status.after', 'operator' => 'notEquals', 'value' => 'New'], true],
            'contains text' => [['field' => 'issue.subject', 'operator' => 'contains', 'value' => 'Ship'], true],
            'contains in a list' => [['field' => 'issue.tags', 'operator' => 'contains', 'value' => 'c'], false],
            'in a list' => [['field' => 'changes.status.after', 'operator' => 'in', 'value' => ['Feedback', 'In Progress']], true],
            'in a text list' => [['field' => 'changes.status.after', 'operator' => 'in', 'value' => 'New, Feedback'], false],
            'notIn' => [['field' => 'changes.status.after', 'operator' => 'notIn', 'value' => 'New, Feedback'], true],
            'empty' => [['field' => 'issue.missing', 'operator' => 'empty'], true],
            'notEmpty' => [['field' => 'issue.subject', 'operator' => 'notEmpty'], true],
            'gt as numbers' => [['field' => 'issue.number', 'operator' => 'gt', 'value' => '9'], true],
            'lt as numbers' => [['field' => 'issue.number', 'operator' => 'lt', 'value' => '9'], false],
            'gt of a missing value' => [['field' => 'issue.missing', 'operator' => 'gt', 'value' => '0'], false],
            'unknown operator' => [['field' => 'issue.number', 'operator' => 'like', 'value' => '42'], false],
        ];
    }

    public function testFilterAndSwitchPickTheirPorts()
    {
        $evaluator = new ConditionEvaluator(new ExpressionResolver());
        $isNew = ['field' => 'changes.status.after', 'operator' => 'equals', 'value' => 'New'];
        $isStarted = ['field' => 'changes.status.after', 'operator' => 'equals', 'value' => 'In Progress'];

        $this->assertSame('pass', $evaluator->filter(['conditions' => [$isStarted]], self::VALUES));
        $this->assertSame('fail', $evaluator->filter(['conditions' => [$isStarted, $isNew]], self::VALUES));
        $this->assertSame('resume', $evaluator->switchPort(['cases' => [
            ['key' => 'start', 'conditions' => [$isNew]],
            ['key' => 'resume', 'conditions' => [$isStarted]],
            ['key' => 'also', 'conditions' => [$isStarted]],
        ]], self::VALUES));
        $this->assertSame('default', $evaluator->switchPort(['cases' => [['key' => 'start', 'conditions' => [$isNew]]]], self::VALUES));
    }

    public function testAUserIsAProjectMemberNamedByIdExpressionRoleOrSponsor()
    {
        $selector = new UserSelector($this->members());
        $expressions = new ExpressionResolver($selector->functions());

        $this->assertSame('user-ada', $selector->select('ownerId', 'user-ada', self::VALUES, $expressions));
        $this->assertSame('user-agent', $selector->select('ownerId', '{issue.assignee}', self::VALUES, $expressions));
        $this->assertSame('user-dev', $selector->select('ownerId', '{role:Developer}', self::VALUES, $expressions));
        $this->assertSame('user-ada', $selector->select('ownerId', '{sponsor:issue.assignee}', self::VALUES, $expressions));
    }

    /**
     * @dataProvider unknownUsers
     */
    public function testAUserOutsideTheProjectOrNobodyFails($setting, array $values, $message)
    {
        $selector = new UserSelector($this->members());

        $this->expectException(WorkflowStepException::class);
        $this->expectExceptionMessage($message);

        $selector->select('assigneeId', $setting, $values, new ExpressionResolver($selector->functions()));
    }

    public function unknownUsers()
    {
        return [
            'not a member' => ['user-stranger', self::VALUES, 'user-stranger, who is not a member of the project'],
            'no one with the role' => ['{role:Auditor}', self::VALUES, 'config.assigneeId names no user'],
            'not an agent' => ['{sponsor:actor.id}', self::VALUES, 'config.assigneeId names no user'],
            'no project' => ['user-ada', ['issue' => []], 'The run has no project'],
        ];
    }

    /**
     * Members of project-1: Ada, the agent Ada sponsors, and a developer.
     *
     * @return ProjectMembers
     */
    private function members()
    {
        return new class extends ProjectMembers {
            public function isMember($projectId, $userId)
            {
                return $projectId === 'project-1' && in_array($userId, ['user-ada', 'user-agent', 'user-dev'], true);
            }

            public function firstWithRole($projectId, $role)
            {
                return $projectId === 'project-1' && $role === 'Developer' ? 'user-dev' : null;
            }

            public function sponsorOf($userId)
            {
                return $userId === 'user-agent' ? 'user-ada' : null;
            }
        };
    }
}
