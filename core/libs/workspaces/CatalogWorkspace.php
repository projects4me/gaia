<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Skill;
use Gaia\MVC\Models\Tool;
use Gaia\MVC\Models\Toolserver;

/**
 * The rule that tool servers and skills belong to one workspace and are only
 * granted to agents of that workspace (Workspaces D-007). A tool follows its
 * tool server. A row or agent without a workspace (legacy rows) is not
 * restricted.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CatalogWorkspace
{
    const WORKSPACE_REQUIRED = 'workspaceId is required: a tool server or skill belongs to a workspace.';

    const WORKSPACE_FIXED = 'The workspace of a tool server or skill cannot be changed.';

    const TOOL_OUTSIDE = 'An agent can only be granted tools of its own workspace.';

    const SKILL_OUTSIDE = 'An agent can only be granted skills of its own workspace.';

    /**
     * Whether a save changes the workspace of a stored row.
     *
     * @param  array|null $snapshot    The stored row, null for a new record
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
     * Whether an agent may be granted the tool server (or the server of the
     * tool): when either has no workspace, or they share it.
     *
     * @param  string|null $agentId
     * @param  string|null $toolServerId
     * @param  string|null $toolId
     * @return bool
     */
    public function mayGrantTool($agentId, $toolServerId, $toolId)
    {
        if ($this->isBlank($toolServerId) && !$this->isBlank($toolId)) {
            $tool = Tool::findFirst([
                'conditions' => 'id = :id: AND deleted = false',
                'bind' => ['id' => (string) $toolId],
            ]);
            $toolServerId = $tool ? $tool->toolServerId : null;
        }

        $server = $this->isBlank($toolServerId) ? null : Toolserver::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $toolServerId],
        ]);

        return $this->sameWorkspace($this->workspaceOfAgent($agentId), $server ? $server->workspaceId : null);
    }

    /**
     * Whether an agent may be granted the skill.
     *
     * @param  string|null $agentId
     * @param  string|null $skillId
     * @return bool
     */
    public function mayGrantSkill($agentId, $skillId)
    {
        $skill = $this->isBlank($skillId) ? null : Skill::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $skillId],
        ]);

        return $this->sameWorkspace($this->workspaceOfAgent($agentId), $skill ? $skill->workspaceId : null);
    }

    /**
     * @param  string|null $agentId
     * @return string|null
     */
    protected function workspaceOfAgent($agentId)
    {
        if ($this->isBlank($agentId)) {
            return null;
        }

        $agent = Agent::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $agentId],
        ]);

        return $agent ? $agent->workspaceId : null;
    }

    /**
     * @param  string|null $agentWorkspaceId
     * @param  string|null $itemWorkspaceId
     * @return bool
     */
    protected function sameWorkspace($agentWorkspaceId, $itemWorkspaceId)
    {
        if ($this->isBlank($agentWorkspaceId) || $this->isBlank($itemWorkspaceId)) {
            return true;
        }

        return (string) $agentWorkspaceId === (string) $itemWorkspaceId;
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    protected function isBlank($value)
    {
        return $value === null || (string) $value === '';
    }
}
