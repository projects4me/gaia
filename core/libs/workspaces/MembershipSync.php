<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

/**
 * Keeps the member set the same in every project of a workspace
 * (Workspaces D-004): a membership created in one project is created in the
 * workspace's other projects, a deleted one is deleted from them, and a
 * project created into a workspace gets the workspace's members.
 *
 * The sync acts as the system: it checks no record scope on the other
 * projects. The rows it writes do not start another round: while a round
 * runs, the hooks of those rows find the sync busy and do nothing.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MembershipSync
{
    /**
     * Whether a round is running (shared by every instance, as each model
     * hook makes its own).
     *
     * @var bool
     */
    private static $running = false;

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
     * Add the user to every other project of the membership's workspace that
     * lacks them.
     *
     * @param  object $membership The created membership (projectId, userId)
     * @return array The memberships the sync created
     */
    public function created($membership)
    {
        return $this->round(function () use ($membership) {
            $otherProjectIds = $this->otherProjectIdsOf($membership);
            $userId = (string) $membership->userId;
            $missing = array_diff($otherProjectIds, $this->memberships->projectIdsOfMember($userId, $otherProjectIds));

            $added = [];
            foreach ($missing as $projectId) {
                $added[] = $this->memberships->add($projectId, $userId);
            }

            return $added;
        });
    }

    /**
     * Remove the user from every other project of the membership's workspace.
     *
     * @param  object $membership The deleted membership (projectId, userId)
     * @return array The memberships the sync deleted
     */
    public function deleted($membership)
    {
        return $this->round(function () use ($membership) {
            $otherProjectIds = $this->otherProjectIdsOf($membership);

            return $this->memberships->remove((string) $membership->userId, $otherProjectIds);
        });
    }

    /**
     * Give a project created into a workspace the members of the workspace's
     * other projects.
     *
     * @param  string      $projectId
     * @param  string|null $workspaceId
     * @return array The memberships the sync created
     */
    public function projectCreated($projectId, $workspaceId)
    {
        return $this->round(function () use ($projectId, $workspaceId) {
            if ($this->isBlank($projectId) || $this->isBlank($workspaceId)) {
                return [];
            }

            $otherProjectIds = $this->memberships->otherProjectIds($workspaceId, $projectId);
            $memberIds = $this->memberships->memberIdsOf($otherProjectIds);
            $present = $this->memberships->memberIdsOf([(string) $projectId]);

            $added = [];
            foreach (array_diff($memberIds, $present) as $userId) {
                $added[] = $this->memberships->add($projectId, $userId);
            }

            return $added;
        });
    }

    /**
     * After a project moved to another workspace (Workspaces D-010): take the
     * old workspace's agents out of the project, then give every project of
     * the new workspace the same members (the union of the moved project's
     * remaining members and the new workspace's members).
     *
     * @param  string      $projectId
     * @param  string|null $fromWorkspaceId The workspace the project left, null for a legacy project
     * @param  string      $toWorkspaceId   The workspace the project is now in
     * @return array The memberships the sync deleted and created
     */
    public function projectMoved($projectId, $fromWorkspaceId, $toWorkspaceId)
    {
        return $this->round(function () use ($projectId, $fromWorkspaceId, $toWorkspaceId) {
            if ($this->isBlank($projectId) || $this->isBlank($toWorkspaceId)) {
                return [];
            }

            $changed = [];
            if (!$this->isBlank($fromWorkspaceId)) {
                foreach ($this->memberships->agentUserIds($fromWorkspaceId) as $agentUserId) {
                    $changed = array_merge(
                        $changed,
                        $this->memberships->remove($agentUserId, [(string) $projectId])
                    );
                }
            }

            $projectIds = $this->memberships->projectIds($toWorkspaceId);
            if (!in_array((string) $projectId, $projectIds, true)) {
                $projectIds[] = (string) $projectId;
            }
            $memberIds = $this->memberships->memberIdsOf($projectIds);
            foreach ($projectIds as $id) {
                $present = $this->memberships->memberIdsOf([$id]);
                foreach (array_diff($memberIds, $present) as $userId) {
                    $changed[] = $this->memberships->add($id, $userId);
                }
            }

            return $changed;
        });
    }

    /**
     * Run one round, unless one is already running.
     *
     * @param  callable $work
     * @return array
     */
    protected function round(callable $work)
    {
        if (self::$running) {
            return [];
        }

        self::$running = true;
        try {
            return $work();
        } finally {
            self::$running = false;
        }
    }

    /**
     * The workspace's other projects, empty for a membership without a
     * project, user or workspace.
     *
     * @param  object $membership
     * @return string[]
     */
    protected function otherProjectIdsOf($membership)
    {
        $projectId = $membership->projectId ?? null;
        if ($this->isBlank($projectId) || $this->isBlank($membership->userId ?? null)) {
            return [];
        }

        $workspaceId = $this->memberships->workspaceIdOf($projectId);
        if ($workspaceId === null) {
            return [];
        }

        return $this->memberships->otherProjectIds($workspaceId, $projectId);
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    private function isBlank($value)
    {
        return $value === null || (string) $value === '';
    }
}
