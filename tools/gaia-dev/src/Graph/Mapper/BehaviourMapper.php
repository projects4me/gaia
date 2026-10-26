<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph\Mapper;

use GaiaDev\Graph\Graph;
use GaiaDev\Graph\Schema;

/**
 * Adds the source details of each model behaviour to its node. The
 * `uses_behaviour` edges (which models declare it) come from ModelMapper, so run
 * this after it.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph\Mapper
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BehaviourMapper
{
    /**
     * @param Graph $graph
     * @param array[] $behaviours BehaviourExtractor records
     */
    public function map(Graph $graph, array $behaviours)
    {
        foreach ($behaviours as $behaviour) {
            $graph->addNode(Schema::BEHAVIOUR, $behaviour['name'], [
                'class' => $behaviour['class'],
                'file' => $behaviour['file'],
                'hooks' => $behaviour['hooks'],
            ]);
        }
    }
}
