<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workspaces;

use Gaia\Libraries\Workspaces\WorkspaceManager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WorkspaceManager: a workspace's manager is a live user, the
 * creator unless another one is given (Workspaces D-022).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceManagerTest extends TestCase
{
    /**
     * Without a manager the creator manages the workspace; a given one is kept.
     *
     * @return void
     */
    public function testManagerDefaultsToTheCreator()
    {
        $rule = $this->rule([]);

        $this->assertSame('creator', $rule->managerFor(null, 'creator'));
        $this->assertSame('creator', $rule->managerFor('', 'creator'));
        $this->assertSame('u2', $rule->managerFor('u2', 'creator'));
        $this->assertNull($rule->managerFor(null, null));
    }

    /**
     * Only a live user may manage a workspace.
     *
     * @return void
     */
    public function testOnlyALiveUserMayManage()
    {
        $rule = $this->rule(['u1']);

        $this->assertNull($rule->managerError('u1'));
        $this->assertSame(WorkspaceManager::NOT_A_USER, $rule->managerError('unknown'));
        $this->assertSame(WorkspaceManager::NOT_A_USER, $rule->managerError(''));
        $this->assertSame(WorkspaceManager::NOT_A_USER, $rule->managerError(null));

        $this->assertTrue($rule->isLiveUser('u1'));
        $this->assertFalse($rule->isLiveUser('unknown'));
        $this->assertFalse($rule->isLiveUser(''));
        $this->assertFalse($rule->isLiveUser(null));
    }

    /**
     * Only a different value counts as a change.
     *
     * @return void
     */
    public function testIsChange()
    {
        $rule = $this->rule([]);

        $this->assertTrue($rule->isChange(null, 'u1'));
        $this->assertFalse($rule->isChange(['managerId' => 'u1'], 'u1'));
        $this->assertTrue($rule->isChange(['managerId' => 'u1'], 'u2'));
        $this->assertTrue($rule->isChange(['managerId' => 'u1'], null));
        $this->assertFalse($rule->isChange(['managerId' => null], ''));
    }

    /**
     * A rule that knows the given live users.
     *
     * @param  array $users ids
     * @return WorkspaceManager
     */
    private function rule(array $users)
    {
        return new class ($users) extends WorkspaceManager {
            private $users;

            public function __construct(array $users)
            {
                $this->users = $users;
            }

            protected function findUser($id)
            {
                return in_array($id, $this->users, true) ? (object) ['id' => $id] : null;
            }
        };
    }
}
