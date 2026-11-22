<?php

namespace Gaia\Tests\Libraries\Workspaces;

use Gaia\Libraries\Workspaces\ProjectWorkspace;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the move rule of {@see ProjectWorkspace} (Workspaces D-002).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProjectWorkspaceTest extends TestCase
{
    /**
     * A new record has no stored row, so it is never a move.
     *
     * @return void
     */
    public function testNewRecordIsNotAMove()
    {
        $this->assertFalse((new ProjectWorkspace())->isMove(null, 'w1'));
    }

    /**
     * Saving the stored workspace again is not a move.
     *
     * @return void
     */
    public function testSameWorkspaceIsNotAMove()
    {
        $this->assertFalse((new ProjectWorkspace())->isMove(['workspaceId' => 'w1'], 'w1'));
    }

    /**
     * A different workspace is a move, and so is setting one on a legacy row.
     *
     * @return void
     */
    public function testChangedWorkspaceIsAMove()
    {
        $rule = new ProjectWorkspace();
        $this->assertTrue($rule->isMove(['workspaceId' => 'w1'], 'w2'));
        $this->assertTrue($rule->isMove(['workspaceId' => null], 'w2'));
    }

    /**
     * A legacy row saved without a workspace keeps working.
     *
     * @return void
     */
    public function testLegacyRowWithoutWorkspaceIsNotAMove()
    {
        $this->assertFalse((new ProjectWorkspace())->isMove(['workspaceId' => null], null));
    }

    /**
     * A new project joins its workspace now (Workspaces D-015).
     *
     * @return void
     */
    public function testNewProjectJoinsNow()
    {
        $this->assertSame('2026-11-22 10:00:00', (new ProjectWorkspace())->joinedAt(null, 'w1', '2026-11-22 10:00:00'));
    }

    /**
     * A move restamps the join date.
     *
     * @return void
     */
    public function testMoveJoinsNow()
    {
        $snapshot = ['workspaceId' => 'w1', 'workspaceJoinedAt' => '2026-01-01 00:00:00'];

        $this->assertSame('2026-11-22 10:00:00', (new ProjectWorkspace())->joinedAt($snapshot, 'w2', '2026-11-22 10:00:00'));
    }

    /**
     * Any other save keeps the stored join date, whatever the request sent.
     *
     * @return void
     */
    public function testOtherSaveKeepsTheStoredJoinDate()
    {
        $rule = new ProjectWorkspace();
        $snapshot = ['workspaceId' => 'w1', 'workspaceJoinedAt' => '2026-01-01 00:00:00'];

        $this->assertSame('2026-01-01 00:00:00', $rule->joinedAt($snapshot, 'w1', '2026-11-22 10:00:00'));
        $this->assertNull($rule->joinedAt(['workspaceId' => 'w1'], 'w1', '2026-11-22 10:00:00'));
    }
}
