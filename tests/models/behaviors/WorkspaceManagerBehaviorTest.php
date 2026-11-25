<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Workspaces\WorkspaceManager;
use Gaia\MVC\Models\Behaviors\workspaceManagerBehavior;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentWorkspaceFakeModel.php';

/**
 * Unit tests for workspaceManagerBehavior: a new workspace is managed by its
 * creator unless another user is given, and a set or changed manager must be
 * a live user (Workspaces D-022).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceManagerBehaviorTest extends TestCase
{
    /**
     * A new workspace without a manager is managed by its creator.
     *
     * @return void
     */
    public function testCreateDefaultsToTheCreator()
    {
        $model = AgentWorkspaceFakeModel::make(['managerId' => null, 'createdUser' => 'unit-user']);

        $this->assertTrue($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame('unit-user', $model->managerId);
        $this->assertSame([], $model->messages);
    }

    /**
     * A new workspace managed by an unknown user is refused.
     *
     * @return void
     */
    public function testCreateWithAnUnknownManagerIsRefused()
    {
        $model = AgentWorkspaceFakeModel::make(['managerId' => 'unit-missing', 'createdUser' => 'unit-user']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['managerId'], $model->messages);
    }

    /**
     * Changing the manager to another live user passes, to an unknown one is
     * refused.
     *
     * @return void
     */
    public function testUpdateChecksTheNewManager()
    {
        $known = AgentWorkspaceFakeModel::make(
            ['managerId' => 'unit-other', 'createdUser' => 'unit-user'],
            ['managerId' => 'unit-user']
        );
        $unknown = AgentWorkspaceFakeModel::make(
            ['managerId' => 'unit-missing', 'createdUser' => 'unit-user'],
            ['managerId' => 'unit-user']
        );

        $this->assertTrue($this->behavior()->notify('beforeValidationOnUpdate', $known));
        $this->assertSame('unit-other', $known->managerId);
        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $unknown));
        $this->assertSame(['managerId'], $unknown->messages);
    }

    /**
     * An unchanged manager is kept, even one since deleted, and a workspace
     * without one stays so until the manager is set.
     *
     * @return void
     */
    public function testUnchangedManagerIsKept()
    {
        $kept = AgentWorkspaceFakeModel::make(
            ['managerId' => 'unit-gone', 'createdUser' => 'unit-user'],
            ['managerId' => 'unit-gone']
        );
        $none = AgentWorkspaceFakeModel::make(
            ['managerId' => null, 'createdUser' => 'unit-user'],
            ['managerId' => null]
        );

        $this->assertTrue($this->behavior()->notify('beforeValidationOnUpdate', $kept));
        $this->assertSame('unit-gone', $kept->managerId);
        $this->assertTrue($this->behavior()->notify('beforeValidationOnUpdate', $none));
        $this->assertNull($none->managerId);
    }

    /**
     * Clearing the manager gives the workspace back to its creator.
     *
     * @return void
     */
    public function testClearedManagerFallsBackToTheCreator()
    {
        $model = AgentWorkspaceFakeModel::make(
            ['managerId' => '', 'createdUser' => 'unit-user'],
            ['managerId' => 'unit-other']
        );

        $this->assertTrue($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame('unit-user', $model->managerId);
    }

    /**
     * The behavior with a rule that knows the users unit-user and unit-other.
     *
     * @return workspaceManagerBehavior
     */
    private function behavior()
    {
        return new class extends workspaceManagerBehavior {
            protected function rule()
            {
                return new class extends WorkspaceManager {
                    protected function findUser($id)
                    {
                        return in_array($id, ['unit-user', 'unit-other'], true) ? (object) ['id' => $id] : null;
                    }
                };
            }
        };
    }
}
