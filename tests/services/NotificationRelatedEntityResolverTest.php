<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Services;

use Gaia\Services\NotificationRelatedEntityResolver;
use Gaia\Services\NotificationRelatedRecordFinder;
use PHPUnit\Framework\TestCase;

/**
 * Old notifications resolve to the relatedTo/relatedId the notification modules set on new ones
 * (agaia-4117). Descriptions and contexts mirror what each module writes.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NotificationRelatedEntityResolverTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|NotificationRelatedRecordFinder
     */
    private $finder;

    /**
     * @var NotificationRelatedEntityResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->finder = $this->createMock(NotificationRelatedRecordFinder::class);
        $this->resolver = new NotificationRelatedEntityResolver($this->finder);
    }

    public function testIssueNotificationRelatesToTheTaggedIssue()
    {
        $this->assertSame(
            ['relatedTo' => 'issue', 'relatedId' => 'issue-1'],
            $this->resolver->resolve(
                '{{User@user-1}} has updated the {{Issue@issue-1}} status to {{status:done}}',
                $this->context(['projectShortcode' => 'prj', 'issueNumber' => 7, 'relatedTo' => 'issue'])
            )
        );
    }

    public function testCommentNotificationRelatesToTheIssue()
    {
        $this->assertSame(
            ['relatedTo' => 'issue', 'relatedId' => 'issue-2'],
            $this->resolver->resolve(
                '{{User@user-1}} has {{Comment@comment-1}} on {{Issue@issue-2}}',
                $this->context(['issueNumber' => 7, 'relatedTo' => 'comment', 'commentId' => 'comment-1'])
            )
        );
    }

    public function testTimelogNotificationRelatesToTheIssue()
    {
        $this->assertSame(
            ['relatedTo' => 'issue', 'relatedId' => 'issue-3'],
            $this->resolver->resolve(
                '{{User@user-1}} has {{Timelog@timelog-1}} time on {{Issue@issue-3}}',
                $this->context(['relatedTo' => 'timelog', 'timelogId' => 'timelog-1'])
            )
        );
    }

    public function testIssueKindWithoutTagIsLookedUpByShortcodeAndNumber()
    {
        $this->finder->expects($this->once())
            ->method('findIssueId')
            ->with('prj', 7)
            ->willReturn('issue-4');

        $this->assertSame(
            ['relatedTo' => 'issue', 'relatedId' => 'issue-4'],
            $this->resolver->resolve(
                'issue updated',
                $this->context(['projectShortcode' => 'prj', 'issueNumber' => 7, 'relatedTo' => 'timelog'])
            )
        );
    }

    public function testMilestoneNotificationRelatesToTheContextMilestone()
    {
        $this->assertSame(
            ['relatedTo' => 'milestone', 'relatedId' => 'milestone-1'],
            $this->resolver->resolve(
                '{{User@user-1}} has created {{Milestone@milestone-1}} milestone for {{Project@project-1}}',
                $this->context(['milestoneId' => 'milestone-1', 'relatedTo' => 'milestone'])
            )
        );
    }

    public function testMilestoneWithoutContextIdUsesTheTag()
    {
        $this->assertSame(
            ['relatedTo' => 'milestone', 'relatedId' => 'milestone-2'],
            $this->resolver->resolve(
                '{{User@user-1}} has created {{Milestone@milestone-2}} milestone for {{Project@project-1}}',
                $this->context(['relatedTo' => 'milestone'])
            )
        );
    }

    public function testMembershipNotificationRelatesToTheTaggedProject()
    {
        $this->finder->expects($this->never())->method('findProjectId');

        $this->assertSame(
            ['relatedTo' => 'project', 'relatedId' => 'project-1'],
            $this->resolver->resolve(
                '{{createdUser:user-1}} has added you to {{Project@project-1}}',
                $this->context(['projectShortcode' => 'prj', 'projectName' => 'Project', 'relatedTo' => 'project'])
            )
        );
    }

    public function testMembershipWithoutTagIsLookedUpByShortcode()
    {
        $this->finder->expects($this->once())
            ->method('findProjectId')
            ->with('prj')
            ->willReturn('project-2');

        $this->assertSame(
            ['relatedTo' => 'project', 'relatedId' => 'project-2'],
            $this->resolver->resolve(
                'you were added to a project',
                $this->context(['projectShortcode' => 'prj', 'relatedTo' => 'project'])
            )
        );
    }

    /**
     * @dataProvider unresolvableProvider
     */
    public function testUnresolvableNotificationsResolveToNull($description, $context)
    {
        $this->assertNull($this->resolver->resolve($description, $context));
    }

    public function unresolvableProvider()
    {
        return [
            'no context' => ['{{Issue@issue-1}}', null],
            'invalid context' => ['{{Issue@issue-1}}', '{not json'],
            'no context.relatedTo' => ['{{Issue@issue-1}}', '{"issueNumber":7}'],
            'unknown kind' => ['{{Issue@issue-1}}', '{"relatedTo":"wiki"}'],
            'issue without tag or lookup keys' => ['issue updated', '{"relatedTo":"issue"}'],
            'milestone without id' => ['milestone created', '{"relatedTo":"milestone"}'],
            'tag longer than an id' => ['{{Issue@' . str_repeat('x', 37) . '}}', '{"relatedTo":"issue"}'],
        ];
    }

    public function testLookupThatFindsNothingResolvesToNull()
    {
        $this->finder->method('findProjectId')->willReturn(null);

        $this->assertNull(
            $this->resolver->resolve('you were added', $this->context(['projectShortcode' => 'gone', 'relatedTo' => 'project']))
        );
    }

    /**
     * @param  array $context
     * @return string
     */
    private function context(array $context)
    {
        return json_encode($context);
    }
}
