<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph\Mapper;

use GaiaDev\Graph\Graph;
use GaiaDev\Graph\Schema;
use RuntimeException;

/**
 * Maps controller and component records onto the graph: component nodes, the
 * source details of each controller and its actions, and which controller uses
 * which component.
 *
 * A `$uses` entry that names no component is an error: the controller would
 * fail to load it.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph\Mapper
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ControllerMapper
{
    /**
     * @param Graph $graph
     * @param array[] $controllers ControllerExtractor records
     * @param array[] $components ComponentExtractor records
     */
    public function map(Graph $graph, array $controllers, array $components)
    {
        foreach ($components as $component) {
            $graph->addNode(Schema::COMPONENT, $component['key'], [
                'kind' => $component['kind'],
                'class' => $component['class'],
                'file' => $component['file'],
                'extends' => $component['extends'],
                'implements' => $component['implements'],
                'hooks' => $component['hooks'],
            ]);
        }

        foreach ($controllers as $controller) {
            $controllerId = $graph->addNode(Schema::CONTROLLER, $controller['name'], [
                'class' => $controller['class'],
                'file' => $controller['file'],
                'extends' => $controller['extends'],
            ]);

            foreach ($controller['actions'] as $action) {
                $graph->addEdge(
                    $controllerId,
                    'has_action',
                    $graph->addNode(Schema::ACTION, $controller['name'] . '.' . $action)
                );
            }

            foreach ($controller['uses'] as $use) {
                $componentId = Schema::nodeId(Schema::COMPONENT, $use);
                if (!$graph->hasNode($componentId)) {
                    throw new RuntimeException("{$controller['class']} uses unknown component {$use}");
                }
                $graph->addEdge($controllerId, 'uses_component', $componentId);
            }
        }
    }
}
