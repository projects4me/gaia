<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Workspaces\CatalogWorkspace;
use Gaia\MVC\Models\Behaviors\catalogWorkspaceBehavior;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentWorkspaceFakeModel.php';

/**
 * Unit tests for catalogWorkspaceBehavior and the CatalogWorkspace rule: a
 * tool server or skill needs a workspace when created and keeps it
 * (Workspaces D-007).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CatalogWorkspaceBehaviorTest extends TestCase
{
    /**
     * A new tool server or skill without a workspace is refused.
     *
     * @return void
     */
    public function testCreateNeedsAWorkspace()
    {
        $model = AgentWorkspaceFakeModel::make(['workspaceId' => '']);

        $this->assertFalse((new catalogWorkspaceBehavior())->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['workspaceId'], $model->messages);
    }

    /**
     * Changing the workspace of a stored row is refused.
     *
     * @return void
     */
    public function testUpdateCannotChangeTheWorkspace()
    {
        $model = AgentWorkspaceFakeModel::make(['workspaceId' => 'w2'], ['workspaceId' => 'w1']);

        $this->assertFalse((new catalogWorkspaceBehavior())->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['workspaceId'], $model->messages);
    }

    /**
     * Keeping the workspace, or filling a legacy row's empty one, passes.
     *
     * @return void
     */
    public function testUpdateKeepingTheWorkspacePasses()
    {
        $same = AgentWorkspaceFakeModel::make(['workspaceId' => 'w1'], ['workspaceId' => 'w1']);
        $legacy = AgentWorkspaceFakeModel::make(['workspaceId' => 'w1'], ['workspaceId' => null]);

        $this->assertNotFalse((new catalogWorkspaceBehavior())->notify('beforeValidationOnUpdate', $same));
        $this->assertNotFalse((new catalogWorkspaceBehavior())->notify('beforeValidationOnUpdate', $legacy));
    }

    /**
     * The rule sees a change only against a stored workspace.
     *
     * @return void
     */
    public function testIsChange()
    {
        $rule = new CatalogWorkspace();

        $this->assertFalse($rule->isChange(null, 'w1'));
        $this->assertFalse($rule->isChange(['workspaceId' => ''], 'w1'));
        $this->assertFalse($rule->isChange(['workspaceId' => 'w1'], 'w1'));
        $this->assertTrue($rule->isChange(['workspaceId' => 'w1'], 'w2'));
    }
}
