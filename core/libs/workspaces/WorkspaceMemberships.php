<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\MVC\Models\Membership;
use Gaia\MVC\Models\Project;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Reads and writes the project memberships of a workspace for
 * {@see MembershipSync}. All queries use bound parameters; deleted projects
 * are not part of a workspace.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceMemberships
{
    /**
     * The workspace of a live project, null when the project is unknown,
     * deleted or has no workspace (legacy rows).
     *
     * @param  string $projectId
     * @return string|null
     */
    public function workspaceIdOf($projectId)
    {
        $project = Project::findFirst([
            'conditions' => 'id = :id: AND deleted = 0',
            'bind' => ['id' => (string) $projectId],
            'columns' => 'workspaceId',
        ]);
        if (!$project || $project->workspaceId === null || $project->workspaceId === '') {
            return null;
        }

        return (string) $project->workspaceId;
    }

    /**
     * Ids of the workspace's live projects, leaving one out.
     *
     * @param  string $workspaceId
     * @param  string $exceptProjectId
     * @return string[]
     */
    public function otherProjectIds($workspaceId, $exceptProjectId)
    {
        $projects = Project::find([
            'conditions' => 'workspaceId = :workspaceId: AND id <> :projectId: AND deleted = 0',
            'bind' => ['workspaceId' => (string) $workspaceId, 'projectId' => (string) $exceptProjectId],
            'columns' => 'id',
        ]);

        $ids = [];
        foreach ($projects as $project) {
            $ids[] = (string) $project->id;
        }

        return $ids;
    }

    /**
     * Ids of all the workspace's live projects.
     *
     * @param  string $workspaceId
     * @return string[]
     */
    public function projectIds($workspaceId)
    {
        return $this->otherProjectIds($workspaceId, '');
    }

    /**
     * Of the given projects, the ones the user is a member of.
     *
     * @param  string   $userId
     * @param  string[] $projectIds
     * @return string[]
     */
    public function projectIdsOfMember($userId, array $projectIds)
    {
        if (empty($projectIds)) {
            return [];
        }

        $ids = [];
        foreach ($this->memberships($projectIds, (string) $userId) as $membership) {
            $ids[] = (string) $membership->projectId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * The users who are members of any of the given projects.
     *
     * @param  string[] $projectIds
     * @return string[]
     */
    public function memberIdsOf(array $projectIds)
    {
        if (empty($projectIds)) {
            return [];
        }

        $ids = [];
        foreach ($this->memberships($projectIds) as $membership) {
            $ids[] = (string) $membership->userId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Create a membership. The save runs the model's own behaviors.
     *
     * @param  string $projectId
     * @param  string $userId
     * @return Membership
     * @throws \RuntimeException The membership could not be saved
     */
    public function add($projectId, $userId)
    {
        $membership = new Membership();
        $membership->id = create_guid();
        $membership->projectId = (string) $projectId;
        $membership->userId = (string) $userId;
        if (!$membership->save()) {
            throw new \RuntimeException('Could not add the workspace membership: ' . $this->messagesOf($membership));
        }

        return $membership;
    }

    /**
     * Delete the user's memberships in the given projects.
     *
     * @param  string   $userId
     * @param  string[] $projectIds
     * @return Membership[] The deleted rows
     * @throws \RuntimeException A membership could not be deleted
     */
    public function remove($userId, array $projectIds)
    {
        if (empty($projectIds)) {
            return [];
        }

        $removed = [];
        foreach ($this->memberships($projectIds, (string) $userId) as $membership) {
            if (!$membership->delete()) {
                throw new \RuntimeException(
                    'Could not remove the workspace membership: ' . $this->messagesOf($membership)
                );
            }
            $removed[] = $membership;
        }

        return $removed;
    }

    /**
     * @param  string[]    $projectIds
     * @param  string|null $userId
     * @return iterable<Membership>
     */
    protected function memberships(array $projectIds, $userId = null)
    {
        $conditions = 'projectId IN ({projectIds:array})';
        $bind = ['projectIds' => array_values(array_map('strval', $projectIds))];
        if ($userId !== null) {
            $conditions .= ' AND userId = :userId:';
            $bind['userId'] = $userId;
        }

        return Membership::find(['conditions' => $conditions, 'bind' => $bind]);
    }

    /**
     * @param  Membership $membership
     * @return string
     */
    protected function messagesOf($membership)
    {
        $messages = [];
        foreach ($membership->getMessages() as $message) {
            $messages[] = $message->getMessage();
        }

        return implode(' ', $messages);
    }
}
