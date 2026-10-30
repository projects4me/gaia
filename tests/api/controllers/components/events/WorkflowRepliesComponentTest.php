<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Controller\Components\Events\WorkflowReplies;

use Gaia\Libraries\Workflows\ActorSteps;
use Gaia\Libraries\Workflows\UserReplies;
use Gaia\Libraries\Workflows\WaitingStepResumer;
use Gaia\MVC\REST\Controllers\Components\Events\WorkflowRepliesComponent;
use Phalcon\Events\Event;
use PHPUnit\Framework\TestCase;

/**
 * A record named like the Issue model, which is what the component reads.
 */
class Issue
{
}

/**
 * Tests that comments and reassignments reach the waiting 'Ask a user'
 * steps, and that a failure never fails the write.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowRepliesComponentTest extends TestCase
{
    /**
     * @var array[] The calls UserReplies received: [method, arguments].
     */
    private $calls = [];

    public function testACommentIsHandedOverWithItsAuthorAndText()
    {
        $this->fire('afterCreate', (object) [
            'id' => 'comment-1', 'relatedTo' => 'issue', 'relatedId' => 'issue-1',
            'createdUser' => 'user-ada', 'comment' => 'Yes',
        ]);
        $this->fire('afterCreate', (object) ['id' => 'issue-2', 'subject' => 'Not a comment']);

        $this->assertSame([['commented', ['issue', 'issue-1', 'user-ada', 'comment-1', 'Yes']]], $this->calls);
    }

    public function testOnlyAnAssigneeChangeIsHandedOver()
    {
        $issue = new Issue();
        $issue->id = 'issue-1';
        $issue->audit = ['assignee' => ['old' => 'user-ada', 'new' => 'user-dev']];
        $this->fire('afterUpdate', $issue);

        $issue->audit = ['subject' => ['old' => 'a', 'new' => 'b']];
        $this->fire('afterUpdate', $issue);

        $this->assertSame([['reassigned', ['issue', 'issue-1', 'user-dev']]], $this->calls);
    }

    public function testAFailureDoesNotFailTheWrite()
    {
        $this->calls = null;

        $this->fire('afterCreate', (object) [
            'id' => 'comment-1', 'relatedTo' => 'issue', 'relatedId' => 'issue-1', 'createdUser' => 'user-ada',
        ]);

        $this->assertNull($this->calls);
    }

    /**
     * @param  string $hook
     * @param  object $model
     * @return void
     */
    private function fire($hook, $model)
    {
        $calls = &$this->calls;
        $resumer = new class implements WaitingStepResumer {
            public function resume($stepId, $port, array $outputs)
            {
            }
        };
        $replies = new class ($calls, $resumer) extends UserReplies {
            private $calls;

            public function __construct(&$calls, WaitingStepResumer $resumer)
            {
                parent::__construct(new ActorSteps(), $resumer);
                $this->calls = &$calls;
            }

            public function commented($relatedTo, $relatedId, $userId, $commentId, $text)
            {
                $this->record('commented', func_get_args());
                return 1;
            }

            public function reassigned($entityType, $entityId, $assigneeId)
            {
                $this->record('reassigned', func_get_args());
                return 1;
            }

            private function record($method, array $arguments)
            {
                if ($this->calls === null) {
                    throw new \RuntimeException('database down');
                }
                $this->calls[] = [$method, $arguments];
            }
        };

        (new WorkflowRepliesComponent($replies))->$hook(new Event('rest:' . $hook, new \stdClass()), null, $model);
    }
}
