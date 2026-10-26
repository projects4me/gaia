<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Query;

/**
 * `gaia-dev fixtures [name]`: the placeholder names api-tester cases can use,
 * where they come from and how many cases use each.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FixturesQuery implements Query
{
    /**
     * {@inheritdoc}
     */
    public function run(GraphReader $graph, array $arguments, array $options): array
    {
        $fixtures = isset($arguments[0]) ? [$graph->find('fixture', $arguments[0])] : $graph->nodes('fixture');

        $lines = [];
        foreach ($fixtures as $fixture) {
            $lines[] = $fixture['key'] . ' | ' . count($graph->in($fixture, 'uses_fixture')) . ' cases | '
                . $fixture['attributes']['file'];
        }

        return [
            'title' => count($lines) . ' fixture placeholders ({name} in case paths and bodies)',
            'fixtures' => $lines,
        ];
    }
}
