<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Workspaces\WorkflowWorkspace;
use Gaia\MVC\Models\Behaviors\workflowWorkspaceBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for workflowWorkspaceBehavior: a required, fixed workspace, a
 * scope inside it and agents inside it on activation.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowWorkspaceBehaviorTest extends TestCase
{
    use WorkflowModelStub;

    public function testCreateNeedsAWorkspace()
    {
        $model = $this->model(['name' => 'Triage']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame([WorkflowWorkspace::WORKSPACE_REQUIRED], $model->messages);
    }

    public function testCreateNeedsAnExistingWorkspace()
    {
        $model = $this->model(['workspaceId' => 'gone']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['workspaceId must be the id of an existing workspace'], $model->messages);
    }

    public function testCreateAcceptsAScopeInsideTheWorkspace()
    {
        $model = $this->model(['workspaceId' => 'ws-1', 'scope' => '["p-1"]']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
    }

    public function testCreateNamesTheForeignProjects()
    {
        $model = $this->model(['workspaceId' => 'ws-1', 'scope' => '["p-1","p-far"]']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame(["scope lists projects outside the workflow's workspace: p-far."], $model->messages);
    }

    public function testUpdateCannotChangeTheWorkspace()
    {
        $model = $this->model(['workspaceId' => 'ws-2'], ['workspaceId' => 'ws-1']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame([WorkflowWorkspace::WORKSPACE_FIXED], $model->messages);
    }

    public function testUpdateChecksTheScopeAgain()
    {
        $model = $this->model(['workspaceId' => 'ws-1', 'scope' => '["p-far"]'], ['workspaceId' => 'ws-1']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
    }

    public function testALegacyWorkflowWithoutAWorkspaceIsNotRestricted()
    {
        $model = $this->model(['scope' => '["p-far"]', 'status' => 'Active'], ['status' => 'Draft']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
    }

    public function testActivatingRefusesAnAgentOfAnotherWorkspace()
    {
        $model = $this->model(['id' => 'wf-1', 'workspaceId' => 'ws-1', 'scope' => '["p-1"]', 'status' => 'Active'],
            ['workspaceId' => 'ws-1', 'status' => 'Draft']);

        $this->assertFalse($this->behavior(['agent-1', 'agent-far'])->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['The workflow runs agents outside its workspace: agent-far.'], $model->messages);
    }

    public function testActivatingAcceptsTheWorkspaceAgents()
    {
        $model = $this->model(['id' => 'wf-1', 'workspaceId' => 'ws-1', 'scope' => '["p-1"]', 'status' => 'Active'],
            ['workspaceId' => 'ws-1', 'status' => 'Draft']);

        $this->assertNotFalse($this->behavior(['agent-1'])->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * @param  string[] $nodeAgents the agents the workflow's nodes name
     * @return workflowWorkspaceBehavior
     */
    private function behavior(array $nodeAgents = [])
    {
        return new class ($nodeAgents) extends workflowWorkspaceBehavior {
            private $nodeAgents;

            public function __construct(array $nodeAgents)
            {
                $this->nodeAgents = $nodeAgents;
            }

            protected function workspaceExists($workspaceId)
            {
                return $workspaceId !== 'gone';
            }

            protected function nodes($workflowId)
            {
                return array_map(function ($id) {
                    return ['category' => 'actor', 'kind' => 'run_agent', 'config' => ['agentId' => $id]];
                }, $this->nodeAgents);
            }

            protected function rule()
            {
                return new WorkflowWorkspaceStub([], ['p-1' => 'ws-1', 'p-far' => 'ws-2'], ['agent-1' => 'ws-1', 'agent-far' => 'ws-2']);
            }
        };
    }
}
