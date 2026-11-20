<?php

namespace Gaia\Tests\Libraries\Workspaces;

use Gaia\Libraries\Workspaces\MemberRemoval;
use Gaia\Libraries\Workspaces\MemberRemovalRefusal;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/MembershipSyncTest.php';

/**
 * Unit tests for {@see MemberRemoval} (Workspaces D-005) against an in-memory
 * store of memberships and open issues.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MemberRemovalTest extends TestCase
{
    /**
     * @param  object[] $issues
     * @return MemberRemoval
     */
    private function removal(array $issues)
    {
        $store = new InMemoryWorkspaceMemberships(
            ['p1' => 'w1', 'p2' => 'w1', 'p3' => 'w2'],
            [['p1', 'u1'], ['p2', 'u1'], ['p1', 'u2'], ['p2', 'u2'], ['p3', 'u3']]
        );

        return new class ($store, $issues) extends MemberRemoval {
            public $issues;

            public function __construct($store, $issues)
            {
                parent::__construct($store);
                $this->issues = $issues;
            }

            protected function openIssues($userId, array $projectIds)
            {
                return $this->issues;
            }
        };
    }

    private function membership()
    {
        return (object) ['projectId' => 'p1', 'userId' => 'u1'];
    }

    private function refusalCode(callable $work)
    {
        try {
            $work();
        } catch (MemberRemovalRefusal $refusal) {
            return $refusal->getCode();
        }
        $this->fail('Expected a refusal');
    }

    /**
     * @return void
     */
    public function testOpenIssuesWithoutHandoverAre409()
    {
        $removal = $this->removal([new \stdClass()]);

        $this->assertSame(409, $this->refusalCode(function () use ($removal) {
            $removal->handOver($this->membership(), null);
        }));
    }

    /**
     * @return void
     */
    public function testNoOpenIssuesNeedsNoHandover()
    {
        $this->assertSame(0, $this->removal([])->handOver($this->membership(), null));
    }

    /**
     * @return void
     */
    public function testHandoverToARemainingMemberReassigns()
    {
        $issues = [new FakeIssue(), new FakeIssue()];

        $count = $this->removal($issues)->handOver($this->membership(), 'u2');

        $this->assertSame(2, $count);
        $this->assertSame('u2', $issues[0]->assignee);
        $this->assertSame('u2', $issues[1]->assignee);
    }

    /**
     * @return void
     */
    public function testWrongTargetsAre422AndReassignNothing()
    {
        $issue = new FakeIssue();
        $removal = $this->removal([$issue]);

        foreach (['u3', 'u1', 'nobody', 'none'] as $target) {
            $this->assertSame(422, $this->refusalCode(function () use ($removal, $target) {
                $removal->handOver($this->membership(), $target);
            }), $target);
        }
        $this->assertNull($issue->assignee);
    }
}

class FakeIssue
{
    public $assignee;

    public function save()
    {
        return true;
    }
}
