<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Workspaces\WorkflowWorkspace;
use Gaia\Libraries\Workspaces\WorkspaceMemberships;

/**
 * A WorkflowWorkspace that reads the workspaces of workflows, projects and
 * agents from arrays instead of the database.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowWorkspaceStub extends WorkflowWorkspace
{
    /**
     * @var array workflowId => workspaceId
     */
    private $workflows;

    /**
     * @var array agentId => workspaceId
     */
    private $agents;

    /**
     * @param array $workflows
     * @param array $projects  projectId => workspaceId
     * @param array $agents
     */
    public function __construct(array $workflows = [], array $projects = [], array $agents = [])
    {
        $memberships = new class ($projects) extends WorkspaceMemberships {
            private $projects;

            public function __construct(array $projects)
            {
                $this->projects = $projects;
            }

            public function workspaceIdOf($projectId)
            {
                return isset($this->projects[$projectId]) ? $this->projects[$projectId] : null;
            }
        };
        parent::__construct($memberships);
        $this->workflows = $workflows;
        $this->agents = $agents;
    }

    public function workspaceIdOfWorkflow($workflowId)
    {
        return isset($this->workflows[$workflowId]) ? $this->workflows[$workflowId] : null;
    }

    protected function workspaceIdOfAgent($agentId)
    {
        return isset($this->agents[$agentId]) ? $this->agents[$agentId] : null;
    }
}
