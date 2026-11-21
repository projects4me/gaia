<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\MVC\Models\Behaviors\agentWorkspaceBehavior;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentWorkspaceFakeModel.php';

/**
 * Unit tests for agentWorkspaceBehavior: an agent needs a workspace when it
 * is created and keeps it (Workspaces D-006).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentWorkspaceBehaviorTest extends TestCase
{
    /**
     * A new agent without a workspace is refused.
     *
     * @return void
     */
    public function testCreateNeedsAWorkspace()
    {
        $model = AgentWorkspaceFakeModel::make(['workspaceId' => '']);

        $this->assertFalse((new agentWorkspaceBehavior())->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['workspaceId'], $model->messages);
    }

    /**
     * Changing the workspace of a stored agent is refused.
     *
     * @return void
     */
    public function testUpdateCannotChangeTheWorkspace()
    {
        $model = AgentWorkspaceFakeModel::make(['workspaceId' => 'w2'], ['workspaceId' => 'w1']);

        $this->assertFalse((new agentWorkspaceBehavior())->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['workspaceId'], $model->messages);
    }

    /**
     * Saving an agent with its own workspace, or a legacy agent without one,
     * passes.
     *
     * @return void
     */
    public function testUpdateKeepingTheWorkspacePasses()
    {
        $same = AgentWorkspaceFakeModel::make(['workspaceId' => 'w1'], ['workspaceId' => 'w1']);
        $legacy = AgentWorkspaceFakeModel::make(['workspaceId' => 'w1'], ['workspaceId' => null]);

        $this->assertNotFalse((new agentWorkspaceBehavior())->notify('beforeValidationOnUpdate', $same));
        $this->assertNotFalse((new agentWorkspaceBehavior())->notify('beforeValidationOnUpdate', $legacy));
    }
}
