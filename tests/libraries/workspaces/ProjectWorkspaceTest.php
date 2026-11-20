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
}
