<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\MVC\Models\Agent;

/**
 * The rule that an agent works only in the projects of its workspace
 * (Workspaces D-006). A user that is not an agent, and an agent without a
 * workspace (legacy rows), are not restricted.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentWorkspace
{
    const WORKSPACE_REQUIRED = 'workspaceId is required: an agent belongs to a workspace.';

    const WORKSPACE_FIXED = 'The workspace of an agent cannot be changed.';

    const MEMBERSHIP_OUTSIDE = 'An agent can only be a member of projects in its own workspace.';

    const ASSIGNEE_OUTSIDE = 'An agent can only be assigned issues of projects in its own workspace.';

    /**
     * @var WorkspaceMemberships
     */
    protected $memberships;

    /**
     * @param WorkspaceMemberships|null $memberships
     */
    public function __construct($memberships = null)
    {
        $this->memberships = $memberships ?: new WorkspaceMemberships();
    }

    /**
     * Whether the user may work in the project: always for a person, for an
     * agent only when the project is in the agent's workspace.
     *
     * @param  string|null $userId
     * @param  string|null $projectId
     * @return bool
     */
    public function mayWorkIn($userId, $projectId)
    {
        $agentWorkspaceId = $this->workspaceIdOfAgentUser($userId);
        if ($agentWorkspaceId === null) {
            return true;
        }

        return $this->memberships->workspaceIdOf($projectId) === $agentWorkspaceId;
    }

    /**
     * The workspace of the agent behind a user, null when the user is no
     * agent or the agent has no workspace yet.
     *
     * @param  string|null $userId
     * @return string|null
     */
    public function workspaceIdOfAgentUser($userId)
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        $agent = Agent::findFirst([
            'conditions' => 'userId = :userId: AND deleted = false',
            'bind' => ['userId' => (string) $userId],
        ]);
        if (!$agent || $agent->workspaceId === null || $agent->workspaceId === '') {
            return null;
        }

        return (string) $agent->workspaceId;
    }

    /**
     * Whether a save changes the agent's workspace.
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
}
