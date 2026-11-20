<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\MVC\Models\Issue;

/**
 * Hands over the open issues of a member who is being removed (Workspaces
 * D-005). A removal takes the member out of the whole workspace, so their open
 * issues in any project of it need a new assignee first.
 *
 * Issues are reassigned through the model, so issue activities, notifications
 * and live events fire as for any assignee change. The caller runs this and
 * the membership delete in one transaction.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MemberRemoval
{
    /**
     * Value of handoverTo that asks to leave the issues unassigned.
     */
    const NONE = 'none';

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
     * Check the handover and reassign the member's open issues.
     *
     * @param  object      $membership The membership being removed (projectId, userId)
     * @param  string|null $handoverTo A user id, 'none', or null when not given
     * @return int The number of issues reassigned
     * @throws MemberRemovalRefusal 409 when open issues need a handover, 422 when the target is wrong
     */
    public function handOver($membership, $handoverTo)
    {
        $userId = (string) $membership->userId;
        $projectIds = $this->workspaceProjectIds((string) $membership->projectId);
        $issues = $this->openIssues($userId, $projectIds);
        $handoverTo = ($handoverTo === null || $handoverTo === '') ? null : (string) $handoverTo;

        if ($handoverTo === null) {
            if (count($issues) > 0) {
                throw new MemberRemovalRefusal(
                    'The member has ' . count($issues) . ' open issue(s) in the workspace; '
                    . 'pass handoverTo with the user who takes them over.',
                    409
                );
            }

            return 0;
        }

        $this->checkTarget($handoverTo, $userId, $projectIds);

        foreach ($issues as $issue) {
            $issue->assignee = $handoverTo;
            if (!$issue->save()) {
                throw new \RuntimeException('Could not hand over an issue: ' . $this->messagesOf($issue));
            }
        }

        return count($issues);
    }

    /**
     * The target must be a remaining member of the workspace, not the removed user.
     * Issue.assignee is required (no unassigned value exists), so 'none' is refused.
     *
     * @param  string   $handoverTo
     * @param  string   $userId
     * @param  string[] $projectIds
     * @return void
     * @throws MemberRemovalRefusal
     */
    protected function checkTarget($handoverTo, $userId, array $projectIds)
    {
        if ($handoverTo === self::NONE) {
            throw new MemberRemovalRefusal(
                'handoverTo=none is not supported: an issue always has an assignee.',
                422
            );
        }
        if ($handoverTo === $userId) {
            throw new MemberRemovalRefusal('handoverTo cannot be the member being removed.', 422);
        }
        if (!in_array($handoverTo, $this->memberships->memberIdsOf($projectIds), true)) {
            throw new MemberRemovalRefusal('handoverTo must be a remaining member of the workspace.', 422);
        }
    }

    /**
     * @param  string $projectId
     * @return string[]
     */
    protected function workspaceProjectIds($projectId)
    {
        $workspaceId = $this->memberships->workspaceIdOf($projectId);
        if ($workspaceId === null) {
            return [$projectId];
        }

        $ids = $this->memberships->projectIds($workspaceId);

        return in_array($projectId, $ids, true) ? $ids : array_merge([$projectId], $ids);
    }

    /**
     * Issues assigned to the user whose status is not done, in the projects.
     *
     * @param  string   $userId
     * @param  string[] $projectIds
     * @return Issue[]
     */
    protected function openIssues($userId, array $projectIds)
    {
        $issues = Issue::find([
            'conditions' => 'assignee = :userId: AND projectId IN ({projectIds:array}) AND deleted = 0'
                . ' AND statusId IN (SELECT s.id FROM Gaia\MVC\Models\Issuestatus s WHERE s.done = 0)',
            'bind' => ['userId' => $userId, 'projectIds' => array_values($projectIds)],
        ]);

        return iterator_to_array($issues, false);
    }

    /**
     * @param  Issue $issue
     * @return string
     */
    protected function messagesOf($issue)
    {
        $messages = [];
        foreach ($issue->getMessages() as $message) {
            $messages[] = $message->getMessage();
        }

        return implode(' ', $messages);
    }
}
