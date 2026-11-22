<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Issues;

/**
 * The live issue links of one type (Issuelink): an edge from each link's
 * source issue to its target. Following the edges of "blocks" from an issue
 * walks the issues it blocks, directly or through others.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Issues
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LinkEdgeSource implements IssueEdgeSource
{
    /**
     * @var string
     */
    private $type;

    /**
     * @param  string $type One of IssueLinkRules::TYPES
     * @throws \InvalidArgumentException For an unknown link type
     */
    public function __construct($type)
    {
        if (!in_array($type, IssueLinkRules::TYPES, true)) {
            throw new \InvalidArgumentException('Unknown issue link type.');
        }
        $this->type = $type;
    }

    /**
     * The type is one of the fixed IssueLinkRules::TYPES (checked in the
     * constructor), so it is safe to write into the constant SELECT.
     *
     * @return string
     */
    public function edgesSql()
    {
        return 'SELECT "sourceIssueId" AS "source", "targetIssueId" AS "target" FROM "issuelinks"'
            . ' WHERE "type" = \'' . $this->type . '\' AND "deleted"::int = 0';
    }
}
