<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Exception\Conflict;
use Gaia\Libraries\Workspaces\WorkspaceShortCode;
use Gaia\MVC\Models\Behaviors\workspaceShortCodeBehavior;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentWorkspaceFakeModel.php';

/**
 * Unit tests for workspaceShortCodeBehavior: a workspace's short code is
 * required, well formed and unique when set or changed (Workspaces D-012).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceShortCodeBehaviorTest extends TestCase
{
    /**
     * A new workspace without a code is refused on shortCode.
     *
     * @return void
     */
    public function testCreateWithoutACodeIsRefused()
    {
        $model = AgentWorkspaceFakeModel::make(['shortCode' => null]);

        $this->assertFalse($this->behavior([])->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['shortCode'], $model->messages);
    }

    /**
     * A malformed code is refused on shortCode.
     *
     * @return void
     */
    public function testCreateWithAMalformedCodeIsRefused()
    {
        $model = AgentWorkspaceFakeModel::make(['shortCode' => 'my space']);

        $this->assertFalse($this->behavior([])->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['shortCode'], $model->messages);
    }

    /**
     * A code another workspace uses is a conflict.
     *
     * @return void
     */
    public function testCreateWithATakenCodeConflicts()
    {
        $model = AgentWorkspaceFakeModel::make(['shortCode' => 'Team']);

        $this->expectException(Conflict::class);
        $this->behavior(['team'])->notify('beforeValidationOnCreate', $model);
    }

    /**
     * A free code passes and is stored in its normalized form.
     *
     * @return void
     */
    public function testCreateWithAFreeCodeIsNormalized()
    {
        $model = AgentWorkspaceFakeModel::make(['shortCode' => ' Team ']);

        $this->assertTrue($this->behavior([])->notify('beforeValidationOnCreate', $model));
        $this->assertSame('team', $model->shortCode);
        $this->assertSame([], $model->messages);
    }

    /**
     * An update that keeps the stored code passes without a lookup, even for a
     * row from before the column that has no code yet.
     *
     * @return void
     */
    public function testUpdateKeepingTheCodePasses()
    {
        $kept = AgentWorkspaceFakeModel::make(['id' => 'w1', 'shortCode' => 'team'], ['shortCode' => 'team']);
        $legacy = AgentWorkspaceFakeModel::make(['id' => 'w2', 'shortCode' => null], ['shortCode' => null]);
        $behavior = $this->behavior(['team']);

        $this->assertTrue($behavior->notify('beforeValidationOnUpdate', $kept));
        $this->assertTrue($behavior->notify('beforeValidationOnUpdate', $legacy));
    }

    /**
     * Clearing the code is refused; changing it to a taken one conflicts.
     *
     * @return void
     */
    public function testUpdateChangingTheCodeIsChecked()
    {
        $cleared = AgentWorkspaceFakeModel::make(['id' => 'w1', 'shortCode' => ''], ['shortCode' => 'team']);
        $this->assertFalse($this->behavior([])->notify('beforeValidationOnUpdate', $cleared));
        $this->assertSame(['shortCode'], $cleared->messages);

        $taken = AgentWorkspaceFakeModel::make(['id' => 'w1', 'shortCode' => 'other'], ['shortCode' => 'team']);
        $this->expectException(Conflict::class);
        $this->behavior(['other'])->notify('beforeValidationOnUpdate', $taken);
    }

    /**
     * The behavior with a rule whose lookup sees only the given codes.
     *
     * @param  array $taken
     * @return workspaceShortCodeBehavior
     */
    private function behavior(array $taken)
    {
        $rule = new class ($taken) extends WorkspaceShortCode {
            private $taken;

            public function __construct(array $taken)
            {
                $this->taken = $taken;
            }

            public function isTaken($code, $exceptId = null)
            {
                return in_array($code, $this->taken, true);
            }
        };

        return new class ($rule) extends workspaceShortCodeBehavior {
            private $fakeRule;

            public function __construct($rule)
            {
                $this->fakeRule = $rule;
            }

            protected function rule()
            {
                return $this->fakeRule;
            }
        };
    }
}
