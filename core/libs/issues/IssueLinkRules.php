<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Issues;

/**
 * The rules a new issue link must meet (Workspaces D-021): a known type, two
 * different live issues in one project or in projects of one workspace, no
 * duplicate, and for "blocks" and "splits" no cycle; an issue is split from
 * one source at most. Callers take the issue graph lock first (see
 * {@see IssueGraph::lock()}) so two writes cannot both pass.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Issues
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueLinkRules
{
    const BLOCKS = 'blocks';
    const RELATES = 'relates';
    const SPLITS = 'splits';

    const TYPES = [self::BLOCKS, self::RELATES, self::SPLITS];

    const UNKNOWN_TYPE = 'The link type must be blocks, relates or splits.';
    const SELF_LINK = 'An issue cannot be linked to itself.';
    const ISSUE_MISSING = 'Both linked issues must exist.';
    const OTHER_WORKSPACE = 'Issues in different workspaces can\'t be linked.';
    const DUPLICATE = 'These issues are already linked this way.';
    const BLOCKS_CYCLE = 'The link would make the issues block each other in a cycle.';
    const SPLITS_CYCLE = 'The link would make an issue split from one of its own splits.';
    const ALREADY_SPLIT = 'The target issue is already split from another issue.';
    const TOO_DEEP = 'The link could not be checked: the chain of linked issues is too deep.';

    /**
     * @var IssueLinks
     */
    private $links;

    /**
     * @var callable Given a link type, returns the IssueGraph over its links
     */
    private $graphFor;

    /**
     * @param IssueLinks $links
     * @param callable   $graphFor function (string $type): IssueGraph
     */
    public function __construct(IssueLinks $links, callable $graphFor)
    {
        $this->links = $links;
        $this->graphFor = $graphFor;
    }

    /**
     * The projects of the two issues, or the first rule the link breaks.
     *
     * @param  string $sourceIssueId
     * @param  string $targetIssueId
     * @param  string $type
     * @return array ['error' => string|null, 'projectId' => string|null, 'targetProjectId' => string|null]
     */
    public function check($sourceIssueId, $targetIssueId, $type)
    {
        $sourceIssueId = (string) $sourceIssueId;
        $targetIssueId = (string) $targetIssueId;

        if (!in_array($type, self::TYPES, true)) {
            return $this->refused(self::UNKNOWN_TYPE);
        }
        if ($sourceIssueId === $targetIssueId) {
            return $this->refused(self::SELF_LINK);
        }

        $source = $this->links->placeOf($sourceIssueId);
        $target = $this->links->placeOf($targetIssueId);
        if ($source === null || $target === null) {
            return $this->refused(self::ISSUE_MISSING);
        }
        if (!$this->sameWorkspace($source, $target)) {
            return $this->refused(self::OTHER_WORKSPACE);
        }

        $error = $this->linkError($sourceIssueId, $targetIssueId, $type);
        if ($error !== null) {
            return $this->refused($error);
        }

        return ['error' => null, 'projectId' => $source['projectId'], 'targetProjectId' => $target['projectId']];
    }

    /**
     * One project, or two projects of the same workspace. Projects without a
     * workspace link only inside themselves.
     *
     * @param  array $source
     * @param  array $target
     * @return bool
     */
    private function sameWorkspace(array $source, array $target)
    {
        if ($source['projectId'] === $target['projectId']) {
            return true;
        }

        return $source['workspaceId'] !== null && $source['workspaceId'] === $target['workspaceId'];
    }

    /**
     * The duplicate, split and cycle rules over the existing links.
     *
     * @param  string $sourceIssueId
     * @param  string $targetIssueId
     * @param  string $type
     * @return string|null
     */
    private function linkError($sourceIssueId, $targetIssueId, $type)
    {
        if ($this->links->exists($sourceIssueId, $targetIssueId, $type, $type === self::RELATES)) {
            return self::DUPLICATE;
        }
        if ($type === self::RELATES) {
            return null;
        }
        if ($type === self::SPLITS && $this->links->hasIncoming($targetIssueId, self::SPLITS)) {
            return self::ALREADY_SPLIT;
        }

        $result = call_user_func($this->graphFor, $type)->check($sourceIssueId, $targetIssueId);
        if ($result === IssueGraph::CYCLE) {
            return $type === self::BLOCKS ? self::BLOCKS_CYCLE : self::SPLITS_CYCLE;
        }
        if ($result === IssueGraph::TOO_DEEP) {
            return self::TOO_DEEP;
        }

        return null;
    }

    /**
     * @param  string $error
     * @return array
     */
    private function refused($error)
    {
        return ['error' => $error, 'projectId' => null, 'targetProjectId' => null];
    }
}
