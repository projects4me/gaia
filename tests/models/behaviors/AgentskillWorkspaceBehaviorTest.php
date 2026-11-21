<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Workspaces\CatalogWorkspace;
use Gaia\MVC\Models\Behaviors\agentskillWorkspaceBehavior;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgenttoolWorkspaceBehaviorTest.php';

/**
 * Unit tests for agentskillWorkspaceBehavior (Workspaces D-007).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentskillWorkspaceBehaviorTest extends TestCase
{
    /**
     * A skill outside the agent's workspace is refused on skillId.
     *
     * @return void
     */
    public function testSkillOutsideTheWorkspaceIsRefused()
    {
        $model = GrantFakeModel::grant(['agentId' => 'a1', 'skillId' => 'k2']);

        $this->assertFalse($this->behavior(false)->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['skillId'], $model->messages);
    }

    /**
     * A skill of the agent's workspace passes.
     *
     * @return void
     */
    public function testSkillInsideTheWorkspacePasses()
    {
        $model = GrantFakeModel::grant(['agentId' => 'a1', 'skillId' => 'k1']);

        $this->assertNotFalse($this->behavior(true)->notify('beforeValidationOnCreate', $model));
        $this->assertSame([], $model->messages);
    }

    /**
     * Pointing an existing grant at a skill of another workspace is refused.
     *
     * @return void
     */
    public function testUpdateToSkillOutsideTheWorkspaceIsRefused()
    {
        $model = GrantFakeModel::grant(['agentId' => 'a1', 'skillId' => 'k2']);

        $this->assertFalse($this->behavior(false)->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['skillId'], $model->messages);
    }

    /**
     * @param  bool $allowed
     * @return agentskillWorkspaceBehavior
     */
    private function behavior($allowed)
    {
        $rule = new class ($allowed) extends CatalogWorkspace {
            private $allowed;

            public function __construct($allowed)
            {
                $this->allowed = $allowed;
            }

            public function mayGrantSkill($agentId, $skillId)
            {
                return $this->allowed;
            }
        };

        return new agentskillWorkspaceBehavior($rule);
    }
}
