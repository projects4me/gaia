<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Query;

/**
 * One gaia-dev query command: answers a question from the graph.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface Query
{
    /**
     * @param GraphReader $graph
     * @param string[] $arguments positional arguments after the command name
     * @param array $options parsed --options (name => value or true)
     * @return array ordered label => scalar, or label => string[] (lines under the label); the
     *               key `title` is the heading line. The same array is the --json output.
     * @throws QueryException when the arguments name something unknown
     */
    public function run(GraphReader $graph, array $arguments, array $options): array;
}
