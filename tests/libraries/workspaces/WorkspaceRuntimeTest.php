<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workspaces;

use Gaia\Libraries\Workspaces\WorkspaceRuntime;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WorkspaceRuntime: a workspace binds to an active runtime
 * service client, and only that client serves the workspace (Workspaces
 * D-007, RBAC D-073).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceRuntimeTest extends TestCase
{
    /**
     * An active runtime may be bound, and no binding is always allowed.
     *
     * @return void
     */
    public function testAnActiveRuntimeOrNoBindingIsAllowed()
    {
        $rule = $this->rule(['rt1' => ['runtime', 'active']], []);

        $this->assertNull($rule->bindingError('rt1'));
        $this->assertNull($rule->bindingError(null));
        $this->assertNull($rule->bindingError(''));
    }

    /**
     * Another kind, a disabled client or an unknown id cannot be bound.
     *
     * @return void
     */
    public function testOnlyAnActiveRuntimeMayBeBound()
    {
        $rule = $this->rule([
            'hermes1' => ['hermes', 'active'],
            'rt-off' => ['runtime', 'disabled'],
        ], []);

        $this->assertSame(WorkspaceRuntime::NOT_A_RUNTIME, $rule->bindingError('hermes1'));
        $this->assertSame(WorkspaceRuntime::NOT_A_RUNTIME, $rule->bindingError('rt-off'));
        $this->assertSame(WorkspaceRuntime::NOT_A_RUNTIME, $rule->bindingError('unknown'));
    }

    /**
     * Only a different value counts as a change, so an unchanged binding to a
     * since disabled client is kept.
     *
     * @return void
     */
    public function testIsChange()
    {
        $rule = $this->rule([], []);

        $this->assertFalse($rule->isChange(null, null));
        $this->assertFalse($rule->isChange(null, ''));
        $this->assertTrue($rule->isChange(null, 'rt1'));
        $this->assertFalse($rule->isChange(['runtimeId' => 'rt1'], 'rt1'));
        $this->assertTrue($rule->isChange(['runtimeId' => 'rt1'], 'rt2'));
        $this->assertTrue($rule->isChange(['runtimeId' => 'rt1'], null));
        $this->assertFalse($rule->isChange(['runtimeId' => null], ''));
    }

    /**
     * A runtime serves only the workspaces bound to it.
     *
     * @return void
     */
    public function testARuntimeServesOnlyItsWorkspaces()
    {
        $rule = $this->rule([], ['w1' => 'rt1', 'w2' => 'rt2', 'w3' => null]);

        $this->assertTrue($rule->serves('rt1', 'w1'));
        $this->assertFalse($rule->serves('rt1', 'w2'));
        $this->assertFalse($rule->serves('rt1', 'w3'));
        $this->assertFalse($rule->serves('rt1', 'unknown'));
        $this->assertFalse($rule->serves('rt1', null));
        $this->assertFalse($rule->serves('', 'w1'));
    }

    /**
     * A rule reading the given service clients and workspaces.
     *
     * @param  array $clients    id => [kind, status]
     * @param  array $workspaces id => runtimeId
     * @return WorkspaceRuntime
     */
    private function rule(array $clients, array $workspaces)
    {
        return new class ($clients, $workspaces) extends WorkspaceRuntime {
            private $clients;
            private $workspaces;

            public function __construct(array $clients, array $workspaces)
            {
                $this->clients = $clients;
                $this->workspaces = $workspaces;
            }

            protected function findServiceClient($id)
            {
                if (!isset($this->clients[$id])) {
                    return null;
                }

                return (object) ['kind' => $this->clients[$id][0], 'status' => $this->clients[$id][1]];
            }

            protected function findWorkspace($id)
            {
                if (!array_key_exists($id, $this->workspaces)) {
                    return null;
                }

                return (object) ['runtimeId' => $this->workspaces[$id]];
            }
        };
    }
}
