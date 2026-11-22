<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Issues;

/**
 * The parent links of issues: an edge from each live issue to its parent
 * (Issue.parentId). Following the edges from an issue walks up its
 * ancestors.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Issues
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ParentEdgeSource implements IssueEdgeSource
{
    /**
     * @return string
     */
    public function edgesSql()
    {
        return 'SELECT "id" AS "source", "parentId" AS "target" FROM "issues"'
            . ' WHERE "parentId" IS NOT NULL AND "parentId" <> \'\' AND "deleted"::int = 0';
    }
}
