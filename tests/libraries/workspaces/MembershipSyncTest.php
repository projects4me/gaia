<?php

namespace Gaia\Tests\Libraries\Workspaces;

use Gaia\Libraries\Workspaces\MembershipSync;
use Gaia\Libraries\Workspaces\WorkspaceMemberships;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see MembershipSync} (Workspaces D-004) against an
 * in-memory store of projects and memberships.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MembershipSyncTest extends TestCase
{
    /**
     * A member added to one project is added to the workspace's other
     * projects that lack them.
     *
     * @return void
     */
    public function testCreateAddsTheMemberToTheOtherProjects()
    {
        $store = new InMemoryWorkspaceMemberships(
            ['p1' => 'w1', 'p2' => 'w1', 'p3' => 'w1', 'p4' => 'w2'],
            [['p1', 'u1'], ['p3', 'u1']]
        );

        $added = (new MembershipSync($store))->created((object) ['projectId' => 'p1', 'userId' => 'u1']);

        $this->assertSame([['p2', 'u1']], $this->pairs($added));
        $this->assertTrue($store->has('p2', 'u1'));
        $this->assertFalse($store->has('p4', 'u1'));
    }

    /**
     * A member removed from one project is removed from all the others.
     *
     * @return void
     */
    public function testDeleteRemovesTheMemberFromTheOtherProjects()
    {
        $store = new InMemoryWorkspaceMemberships(
            ['p1' => 'w1', 'p2' => 'w1', 'p3' => 'w1', 'p4' => 'w2'],
            [['p2', 'u1'], ['p3', 'u1'], ['p4', 'u1'], ['p2', 'u2']]
        );

        $removed = (new MembershipSync($store))->deleted((object) ['projectId' => 'p1', 'userId' => 'u1']);

        $this->assertSame([['p2', 'u1'], ['p3', 'u1']], $this->pairs($removed));
        $this->assertFalse($store->has('p2', 'u1'));
        $this->assertTrue($store->has('p4', 'u1'));
        $this->assertTrue($store->has('p2', 'u2'));
    }

    /**
     * A one-project workspace, or a project without one, has nothing to sync.
     *
     * @return void
     */
    public function testOneProjectOrNoWorkspaceSyncsNothing()
    {
        $store = new InMemoryWorkspaceMemberships(['p1' => 'w1', 'p9' => null], []);
        $sync = new MembershipSync($store);

        $this->assertSame([], $sync->created((object) ['projectId' => 'p1', 'userId' => 'u1']));
        $this->assertSame([], $sync->created((object) ['projectId' => 'p9', 'userId' => 'u1']));
        $this->assertSame([], $sync->deleted((object) ['projectId' => 'p1', 'userId' => 'u1']));
        $this->assertSame([], $sync->created((object) ['projectId' => null, 'userId' => 'u1']));
        $this->assertSame(0, $store->writes);
    }

    /**
     * Rows the sync writes do not start another round.
     *
     * @return void
     */
    public function testRowsWrittenByTheSyncDoNotSyncAgain()
    {
        $store = new InMemoryWorkspaceMemberships(['p1' => 'w1', 'p2' => 'w1', 'p3' => 'w1'], []);
        $sync = new MembershipSync($store);
        $nested = [];
        $store->onWrite = function ($projectId, $userId) use ($sync, &$nested) {
            $nested[] = $sync->created((object) ['projectId' => $projectId, 'userId' => $userId]);
        };

        $added = $sync->created((object) ['projectId' => 'p1', 'userId' => 'u1']);

        $this->assertCount(2, $added);
        $this->assertSame([[], []], $nested);
        $this->assertSame(2, $store->writes);
    }

    /**
     * A failed write ends the round, so the next write syncs again.
     *
     * @return void
     */
    public function testAFailedRoundDoesNotBlockTheNextOne()
    {
        $store = new InMemoryWorkspaceMemberships(['p1' => 'w1', 'p2' => 'w1'], []);
        $sync = new MembershipSync($store);
        $store->fail = true;

        try {
            $sync->created((object) ['projectId' => 'p1', 'userId' => 'u1']);
            $this->fail('The failed write should throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame('write failed', $e->getMessage());
        }

        $store->fail = false;
        $this->assertCount(1, $sync->created((object) ['projectId' => 'p1', 'userId' => 'u1']));
    }

    /**
     * A project created into a workspace gets the members of its other
     * projects, once each.
     *
     * @return void
     */
    public function testProjectCreatedGetsTheWorkspaceMembers()
    {
        $store = new InMemoryWorkspaceMemberships(
            ['p1' => 'w1', 'p2' => 'w1', 'p3' => 'w1', 'p4' => 'w2'],
            [['p1', 'u1'], ['p2', 'u1'], ['p2', 'u2'], ['p3', 'u3'], ['p4', 'u4']]
        );

        $added = (new MembershipSync($store))->projectCreated('p3', 'w1');

        $this->assertSame([['p3', 'u1'], ['p3', 'u2']], $this->pairs($added));
        $this->assertFalse($store->has('p3', 'u4'));
    }

    /**
     * A project in a new one-project workspace gets no members.
     *
     * @return void
     */
    public function testProjectCreatedIntoANewWorkspaceGetsNoMembers()
    {
        $store = new InMemoryWorkspaceMemberships(['p1' => 'w1', 'p2' => 'w2'], [['p1', 'u1']]);
        $sync = new MembershipSync($store);

        $this->assertSame([], $sync->projectCreated('p2', 'w2'));
        $this->assertSame([], $sync->projectCreated('p2', null));
        $this->assertSame(0, $store->writes);
    }

    /**
     * @param  array $memberships
     * @return array
     */
    private function pairs(array $memberships)
    {
        $pairs = array_map(function ($membership) {
            return [$membership->projectId, $membership->userId];
        }, $memberships);
        sort($pairs);

        return $pairs;
    }
}

/**
 * In-memory {@see WorkspaceMemberships}: projects map to workspaces, and
 * memberships are [projectId, userId] pairs.
 */
class InMemoryWorkspaceMemberships extends WorkspaceMemberships
{
    /** @var array<string, string|null> */
    private $projects;

    /** @var array */
    private $rows;

    /** @var int */
    public $writes = 0;

    /** @var bool */
    public $fail = false;

    /** @var callable|null Called after each add, as the saved row's hook would */
    public $onWrite;

    /**
     * @param array $projects
     * @param array $rows
     */
    public function __construct(array $projects, array $rows)
    {
        $this->projects = $projects;
        $this->rows = $rows;
    }

    public function workspaceIdOf($projectId)
    {
        return $this->projects[$projectId] ?? null;
    }

    public function otherProjectIds($workspaceId, $exceptProjectId)
    {
        $ids = [];
        foreach ($this->projects as $projectId => $projectWorkspaceId) {
            if ($projectWorkspaceId === $workspaceId && $projectId !== $exceptProjectId) {
                $ids[] = (string) $projectId;
            }
        }

        return $ids;
    }

    public function projectIdsOfMember($userId, array $projectIds)
    {
        $ids = [];
        foreach ($this->rows as $row) {
            if ($row[1] === $userId && in_array($row[0], $projectIds, true)) {
                $ids[] = $row[0];
            }
        }

        return array_values(array_unique($ids));
    }

    public function memberIdsOf(array $projectIds)
    {
        $ids = [];
        foreach ($this->rows as $row) {
            if (in_array($row[0], $projectIds, true)) {
                $ids[] = $row[1];
            }
        }

        return array_values(array_unique($ids));
    }

    public function add($projectId, $userId)
    {
        if ($this->fail) {
            throw new \RuntimeException('write failed');
        }
        $this->writes++;
        $this->rows[] = [(string) $projectId, (string) $userId];
        if ($this->onWrite) {
            ($this->onWrite)($projectId, $userId);
        }

        return (object) ['projectId' => (string) $projectId, 'userId' => (string) $userId];
    }

    public function remove($userId, array $projectIds)
    {
        $removed = [];
        foreach ($this->rows as $index => $row) {
            if ($row[1] === $userId && in_array($row[0], $projectIds, true)) {
                $this->writes++;
                unset($this->rows[$index]);
                $removed[] = (object) ['projectId' => $row[0], 'userId' => $row[1]];
            }
        }

        return $removed;
    }

    /**
     * @param  string $projectId
     * @param  string $userId
     * @return bool
     */
    public function has($projectId, $userId)
    {
        return in_array([$projectId, $userId], array_values($this->rows), true);
    }
}
