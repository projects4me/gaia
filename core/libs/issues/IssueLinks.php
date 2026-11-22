<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Issues;

use Gaia\MVC\Models\Issue;
use Gaia\MVC\Models\Issuelink;
use Gaia\MVC\Models\Project;

/**
 * Reads and writes issue links for {@see IssueLinkRules} and the issue
 * delete cascade. All queries use bound parameters and see only live rows.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Issues
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueLinks
{
    /**
     * The project and workspace of a live issue, or null when the issue is
     * unknown or deleted. The workspace is null when the project has none.
     *
     * @param  string $issueId
     * @return array|null ['projectId' => string, 'workspaceId' => string|null]
     */
    public function placeOf($issueId)
    {
        $issue = Issue::findFirst([
            'conditions' => 'id = :id: AND deleted = 0',
            'bind' => ['id' => (string) $issueId],
            'columns' => 'projectId',
        ]);
        if (!$issue || (string) $issue->projectId === '') {
            return null;
        }

        $project = Project::findFirst([
            'conditions' => 'id = :id: AND deleted = 0',
            'bind' => ['id' => (string) $issue->projectId],
            'columns' => 'workspaceId',
        ]);
        $workspaceId = $project ? (string) $project->workspaceId : '';

        return [
            'projectId' => (string) $issue->projectId,
            'workspaceId' => $workspaceId === '' ? null : $workspaceId,
        ];
    }

    /**
     * Whether a live link of the type runs from source to target (or, when
     * $eitherDirection, from target to source too).
     *
     * @param  string $sourceIssueId
     * @param  string $targetIssueId
     * @param  string $type
     * @param  bool   $eitherDirection
     * @return bool
     */
    public function exists($sourceIssueId, $targetIssueId, $type, $eitherDirection = false)
    {
        $conditions = 'sourceIssueId = :source: AND targetIssueId = :target:';
        if ($eitherDirection) {
            $conditions = '((' . $conditions . ') OR (sourceIssueId = :target: AND targetIssueId = :source:))';
        }

        return Issuelink::count([
            'conditions' => $conditions . ' AND type = :type: AND deleted = false',
            'bind' => [
                'source' => (string) $sourceIssueId,
                'target' => (string) $targetIssueId,
                'type' => (string) $type,
            ],
        ]) > 0;
    }

    /**
     * Whether the issue is already the target of a live link of the type.
     *
     * @param  string $targetIssueId
     * @param  string $type
     * @return bool
     */
    public function hasIncoming($targetIssueId, $type)
    {
        return Issuelink::count([
            'conditions' => 'targetIssueId = :target: AND type = :type: AND deleted = false',
            'bind' => ['target' => (string) $targetIssueId, 'type' => (string) $type],
        ]) > 0;
    }

    /**
     * The live links from or to the issue.
     *
     * @param  string $issueId
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public function of($issueId)
    {
        return Issuelink::find([
            'conditions' => '(sourceIssueId = :id: OR targetIssueId = :id:) AND deleted = false',
            'bind' => ['id' => (string) $issueId],
        ]);
    }
}
