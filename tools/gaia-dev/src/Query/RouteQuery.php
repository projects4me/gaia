<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Query;

/**
 * `gaia-dev route <endpoint|path>`: controller, action, method, type, the
 * RestController hook sequence for that action and the components that fire.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RouteQuery implements Query
{
    /**
     * REST action => events RestController fires after authorizing it (see
     * core/mvc/controllers/RestController.php). A component handles an event with the
     * method of the same name without the `rest:` prefix.
     */
    private const EVENTS = [
        'get' => ['rest:afterRead'],
        'list' => ['rest:afterRead'],
        'post' => ['rest:beforeCreate', 'rest:afterCreate'],
        'put' => ['rest:afterUpdate'],
        'patch' => ['rest:afterUpdate'],
        'patchCollection' => ['rest:afterUpdate'],
        'delete' => ['rest:afterDelete'],
    ];

    /**
     * {@inheritdoc}
     */
    public function run(GraphReader $graph, array $arguments, array $options): array
    {
        if (!isset($arguments[0])) {
            throw new QueryException('Usage: gaia-dev route <endpoint|path>, e.g. idea or "GET /api/v1/idea/{id}"');
        }
        $name = implode(' ', $arguments);
        $routes = $this->match($graph, $name);

        if (count($routes) === 1) {
            return $this->detail($graph, $routes[0]);
        }

        $lines = [];
        foreach ($routes as $route) {
            $lines[] = $this->summary($graph, $route);
        }

        return ['title' => count($routes) . ' routes for ' . $name, 'routes' => $lines];
    }

    /**
     * @param GraphReader $graph
     * @param string $name route key, path (any method) or controller key
     * @return array[]
     */
    private function match(GraphReader $graph, string $name): array
    {
        $routes = $graph->nodes('route');
        if (isset($routes[$name])) {
            return [$routes[$name]];
        }
        $path = preg_replace('/^[A-Za-z]+ /', '', $name);
        $method = $path === $name ? null : strtoupper(strtok($name, ' '));
        $found = array_values(array_filter($routes, function ($route) use ($path, $method) {
            return $route['attributes']['path'] === $path
                && ($method === null || $route['attributes']['method'] === $method);
        }));
        if (!$found) {
            $controller = $graph->node('controller', strtolower($name));
            if ($controller !== null) {
                foreach ($graph->out($controller, 'has_action') as $action) {
                    $found = array_merge($found, $graph->in($action, 'handled_by'));
                }
            }
        }
        if (!$found) {
            $known = array_merge(array_keys($graph->nodes('controller')), array_keys($routes));
            throw new QueryException($graph->unknown('route', $name, $known));
        }
        usort($found, function ($a, $b) {
            return strcmp($a['key'], $b['key']);
        });

        return $found;
    }

    /**
     * @param GraphReader $graph
     * @param array $route
     * @return array|null the action node
     */
    private function action(GraphReader $graph, array $route): ?array
    {
        $actions = $graph->out($route, 'handled_by');

        return $actions ? $actions[0] : null;
    }

    /**
     * @param GraphReader $graph
     * @param array $route
     * @return string
     */
    private function summary(GraphReader $graph, array $route): string
    {
        $action = $this->action($graph, $route);
        $permissions = $action ? $this->keys($graph->out($action, 'requires')) : [];

        return $route['key'] . ' -> ' . ($action ? $action['key'] : '-')
            . ($permissions ? ' [needs ' . implode(',', $permissions) . ']' : '');
    }

    /**
     * @param array[] $nodes
     * @return string[]
     */
    private function keys(array $nodes): array
    {
        return array_map(function ($node) {
            return $node['key'];
        }, $nodes);
    }

    /**
     * @param GraphReader $graph
     * @param array $route
     * @return array
     */
    private function detail(GraphReader $graph, array $route): array
    {
        $attributes = $route['attributes'];
        $answer = [
            'title' => 'Route ' . $route['key'] . ' (' . $attributes['type'] . ', ' . $attributes['version'] . ')',
        ];
        $action = $this->action($graph, $route);
        if ($action === null) {
            return $answer;
        }
        $actionName = substr($action['key'], strrpos($action['key'], '.') + 1);
        $controllers = $graph->in($action, 'has_action');
        $controller = $controllers ? $controllers[0] : null;

        $answer['controller'] = $controller ? $controller['attributes']['class'] : '-';
        $answer['action'] = $actionName . 'Action';
        $answer['method'] = $attributes['method'];
        $answer['requires'] = implode(', ', $this->keys($graph->out($action, 'requires'))) ?: '-';

        $events = isset(self::EVENTS[$actionName]) && $attributes['type'] === 'rest' ? self::EVENTS[$actionName] : [];
        $answer['sequence'] = array_merge(
            ['beforeExecuteRoute: setUser, authorize', $actionName . 'Action'],
            array_map(function ($event) {
                return 'fire ' . $event;
            }, $events)
        );

        $components = [];
        if ($controller !== null) {
            foreach ($graph->out($controller, 'uses_component') as $component) {
                $hooks = [];
                foreach ($events as $event) {
                    $hook = substr($event, strlen('rest:'));
                    if (in_array($hook, $component['attributes']['hooks'], true)) {
                        $hooks[] = $hook;
                    }
                }
                if ($hooks) {
                    $components[] = $component['key'] . ' on ' . implode(', ', $hooks);
                }
            }
        }
        $answer['components'] = $components;

        return $answer;
    }
}
