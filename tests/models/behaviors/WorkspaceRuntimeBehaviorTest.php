<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Workspaces\WorkspaceRuntime;
use Gaia\MVC\Models\Behaviors\workspaceRuntimeBehavior;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentWorkspaceFakeModel.php';

/**
 * Unit tests for workspaceRuntimeBehavior: a workspace's runtimeId is
 * checked when it is set or changed, and an unchanged binding is kept
 * (Workspaces D-007).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceRuntimeBehaviorTest extends TestCase
{
    /**
     * A new workspace bound to a client that is no runtime is refused.
     *
     * @return void
     */
    public function testCreateWithAnUnknownRuntimeIsRefused()
    {
        $model = AgentWorkspaceFakeModel::make(['runtimeId' => 'unit-no-such-serviceclient']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['runtimeId'], $model->messages);
    }

    /**
     * Changing the binding to a client that is no runtime is refused.
     *
     * @return void
     */
    public function testUpdateToAnUnknownRuntimeIsRefused()
    {
        $model = AgentWorkspaceFakeModel::make(
            ['runtimeId' => 'unit-no-such-serviceclient'],
            ['runtimeId' => 'unit-old-runtime']
        );

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['runtimeId'], $model->messages);
    }

    /**
     * No binding, an unchanged binding (even to a client since disabled or
     * deleted) and removing the binding all pass.
     *
     * @return void
     */
    public function testUnchangedOrClearedBindingPasses()
    {
        $none = AgentWorkspaceFakeModel::make(['runtimeId' => null]);
        $kept = AgentWorkspaceFakeModel::make(['runtimeId' => 'unit-gone'], ['runtimeId' => 'unit-gone']);
        $cleared = AgentWorkspaceFakeModel::make(['runtimeId' => null], ['runtimeId' => 'unit-gone']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $none));
        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $kept));
        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $cleared));
        $this->assertSame([], $kept->messages);
    }

    /**
     * The behavior with a rule that knows no service client, so any binding
     * it looks up is refused.
     *
     * @return workspaceRuntimeBehavior
     */
    private function behavior()
    {
        return new class extends workspaceRuntimeBehavior {
            protected function rule()
            {
                return new class extends WorkspaceRuntime {
                    protected function findServiceClient($id)
                    {
                        return null;
                    }
                };
            }
        };
    }
}
