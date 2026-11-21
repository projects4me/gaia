<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\Libraries\Workflows\NodeKindCatalog;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Workflow;

/**
 * The rule that a workflow belongs to one workspace and only runs on that
 * workspace's projects and with its agents (Workspaces D-007). A workflow
 * without a workspace (legacy row) is not restricted.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowWorkspace
{
    const WORKSPACE_REQUIRED = 'workspaceId is required: a workflow belongs to a workspace.';

    const WORKSPACE_FIXED = 'The workspace of a workflow cannot be changed.';

    /**
     * @var WorkspaceMemberships
     */
    protected $memberships;

    /**
     * @var NodeKindCatalog
     */
    protected $catalog;

    /**
     * @param WorkspaceMemberships|null $memberships
     * @param NodeKindCatalog|null      $catalog
     */
    public function __construct($memberships = null, $catalog = null)
    {
        $this->memberships = $memberships ?: new WorkspaceMemberships();
        $this->catalog = $catalog ?: new NodeKindCatalog();
    }

    /**
     * The scope projects that are not in the workspace.
     *
     * @param  string   $workspaceId
     * @param  string[] $projectIds
     * @return string[]
     */
    public function foreignProjectIds($workspaceId, array $projectIds)
    {
        return array_values(array_filter($projectIds, function ($projectId) use ($workspaceId) {
            return $this->memberships->workspaceIdOf($projectId) !== (string) $workspaceId;
        }));
    }

    /**
     * The agents that are not in the workspace (an agent without one counts
     * as outside it).
     *
     * @param  string   $workspaceId
     * @param  string[] $agentIds
     * @return string[]
     */
    public function foreignAgentIds($workspaceId, array $agentIds)
    {
        return array_values(array_filter($agentIds, function ($agentId) use ($workspaceId) {
            return $this->workspaceIdOfAgent($agentId) !== (string) $workspaceId;
        }));
    }

    /**
     * The agent ids that nodes name in their config (expressions left out).
     *
     * @param  array $nodes arrays with category, kind and config
     * @return string[]
     */
    public function agentIdsOf(array $nodes)
    {
        $ids = [];
        foreach ($nodes as $node) {
            $category = (string) $node['category'];
            $kind = (string) $node['kind'];
            if (!is_array($node['config']) || !$this->catalog->has($category, $kind)) {
                continue;
            }
            foreach ($this->catalog->references($category, $kind, $node['config']) as $reference) {
                if ($reference['entityType'] === 'Agent') {
                    $ids[$reference['entityId']] = $reference['entityId'];
                }
            }
        }

        return array_values($ids);
    }

    /**
     * The workspace of a live workflow, null when it has none or is unknown.
     *
     * @param  string $workflowId
     * @return string|null
     */
    public function workspaceIdOfWorkflow($workflowId)
    {
        $workflow = Workflow::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $workflowId],
            'columns' => 'workspaceId',
        ]);
        if (!$workflow || $workflow->workspaceId === null || $workflow->workspaceId === '') {
            return null;
        }

        return (string) $workflow->workspaceId;
    }

    /**
     * Whether a save changes the workflow's workspace.
     *
     * @param  array|null $snapshot    The stored row
     * @param  mixed      $workspaceId The value being saved
     * @return bool
     */
    public function isChange($snapshot, $workspaceId)
    {
        if (!is_array($snapshot) || empty($snapshot['workspaceId'])) {
            return false;
        }

        return (string) $snapshot['workspaceId'] !== (string) $workspaceId;
    }

    /**
     * @param  string $agentId
     * @return string|null
     */
    protected function workspaceIdOfAgent($agentId)
    {
        $agent = Agent::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $agentId],
        ]);
        if (!$agent || $agent->workspaceId === null || $agent->workspaceId === '') {
            return null;
        }

        return (string) $agent->workspaceId;
    }
}
