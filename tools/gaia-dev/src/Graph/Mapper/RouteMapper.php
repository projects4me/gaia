<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph\Mapper;

use GaiaDev\Graph\Graph;
use GaiaDev\Graph\Schema;

/**
 * Maps route records onto route, controller and action nodes, and links each
 * REST controller (marked `rest`) to the model it serves.
 *
 * Run it after ModelMapper: a REST controller serves the model whose lowercase
 * name equals the resource name (`issue` -> `Issue`), the convention
 * `RestController` follows.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph\Mapper
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RouteMapper
{
    /**
     * @param Graph $graph
     * @param array[] $routes RouteExtractor records
     */
    public function map(Graph $graph, array $routes)
    {
        $modelsByResource = [];
        foreach ($graph->nodesOfType(Schema::MODEL) as $id => $model) {
            $modelsByResource[strtolower($model['key'])] = $id;
        }

        foreach ($routes as $route) {
            $routeId = $graph->addNode(Schema::ROUTE, self::routeKey($route), [
                'type' => $route['type'],
                'name' => $route['name'],
                'version' => $route['version'],
                'method' => $route['method'],
                'path' => $route['path'],
            ]);
            $controllerId = $graph->addNode(
                Schema::CONTROLLER,
                $route['controller'],
                $route['type'] === 'rest' ? ['rest' => true] : []
            );
            $actionId = $graph->addNode(Schema::ACTION, $route['controller'] . '.' . $route['action']);

            $graph->addEdge($controllerId, 'has_action', $actionId);
            $graph->addEdge($routeId, 'handled_by', $actionId);

            if ($route['type'] === 'rest' && isset($modelsByResource[$route['controller']])) {
                $graph->addEdge($controllerId, 'serves', $modelsByResource[$route['controller']]);
            }
        }
    }

    /**
     * `<METHOD> <path>`, or `system:<name>` for system routes, which have no path.
     *
     * @param array $route
     * @return string
     */
    public static function routeKey(array $route): string
    {
        if ($route['path'] === null) {
            return $route['type'] . ':' . $route['name'];
        }

        return $route['method'] . ' ' . $route['path'];
    }
}
