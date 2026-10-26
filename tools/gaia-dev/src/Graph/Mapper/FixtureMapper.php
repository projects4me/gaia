<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph\Mapper;

use GaiaDev\Graph\Graph;
use GaiaDev\Graph\Schema;

/**
 * Maps the api-tester fixtures onto profile nodes (auth profiles) and fixture
 * nodes (placeholder values).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph\Mapper
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FixtureMapper
{
    /**
     * @param Graph $graph
     * @param array $fixtures FixtureExtractor record
     */
    public function map(Graph $graph, array $fixtures)
    {
        foreach ($fixtures['profiles'] as $profile) {
            $graph->addNode(Schema::PROFILE, $profile, ['file' => $fixtures['file']]);
        }

        foreach ($fixtures['values'] as $key) {
            $graph->addNode(Schema::FIXTURE, $key, ['file' => $fixtures['file']]);
        }
    }
}
