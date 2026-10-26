<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Docs;

use GaiaDev\Query\GraphReader;

/**
 * Builds some of the generated pages from the graph.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Docs
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface PageBuilder
{
    /**
     * @param GraphReader $graph
     * @return string[] path relative to docs/generated => page content
     */
    public function build(GraphReader $graph): array;
}
