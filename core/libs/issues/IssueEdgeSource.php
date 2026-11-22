<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Issues;

/**
 * One kind of directed edge between issues (a parent link, a "blocks"
 * link, ...), as {@see IssueGraph} walks it.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Issues
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface IssueEdgeSource
{
    /**
     * A constant SELECT, without placeholders, that yields every edge as the
     * columns "source" and "target" (issue ids). It must not filter by ACL:
     * the walk sees the whole graph.
     *
     * @return string
     */
    public function edgesSql();
}
