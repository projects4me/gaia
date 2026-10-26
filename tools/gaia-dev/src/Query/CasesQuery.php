<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Query;

/**
 * `gaia-dev cases <endpoint|id>`: the api-tester cases for an endpoint (a
 * resource, a path or a route) in compact form, or one case by id.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CasesQuery implements Query
{
    private const DEFAULT_LIMIT = 25;

    /**
     * {@inheritdoc}
     */
    public function run(GraphReader $graph, array $arguments, array $options): array
    {
        if (!isset($arguments[0])) {
            throw new QueryException(
                'Usage: gaia-dev cases <resource|path|route|case id> [--method=GET] [--profile=name] [--all]'
            );
        }
        $name = implode(' ', $arguments);
        $cases = $this->match($graph, $name);
        $total = count($cases);

        if (isset($options['method']) && is_string($options['method'])) {
            $cases = array_values(array_filter($cases, function ($case) use ($options) {
                return strcasecmp($case['attributes']['method'], $options['method']) === 0;
            }));
        }
        if (isset($options['profile']) && is_string($options['profile'])) {
            $cases = array_values(array_filter($cases, function ($case) use ($graph, $options) {
                return $this->profile($graph, $case) === $options['profile'];
            }));
        }

        $limit = !empty($options['all']) ? PHP_INT_MAX : self::DEFAULT_LIMIT;
        $lines = [];
        foreach (array_slice($cases, 0, $limit) as $case) {
            $lines[] = $this->describe($graph, $case);
        }
        if (count($cases) > $limit) {
            $lines[] = '... ' . (count($cases) - $limit) . ' more; narrow with --method= / --profile= or use --all';
        }

        return [
            'title' => count($cases) . ($total === count($cases) ? '' : " of {$total}") . ' cases for ' . $name,
            'cases' => $lines,
        ];
    }

    /**
     * @param GraphReader $graph
     * @param string $name
     * @return array[]
     */
    private function match(GraphReader $graph, string $name): array
    {
        $all = $graph->nodes('case');
        if (isset($all[$name])) {
            return [$all[$name]];
        }
        $controller = $graph->node('controller', strtolower($name));
        if ($controller !== null) {
            return $graph->in($controller, 'covers');
        }
        $found = [];
        foreach ($graph->nodes('route') as $route) {
            if ($route['key'] === $name || $route['attributes']['path'] === $name) {
                $found = array_merge($found, $graph->in($route, 'exercises'));
            }
        }
        if (!$found) {
            $known = array_merge(array_keys($graph->nodes('controller')), array_keys($all));
            throw new QueryException($graph->unknown('endpoint or case', $name, $known));
        }

        return $found;
    }

    /**
     * @param GraphReader $graph
     * @param array $case
     * @return string
     */
    private function profile(GraphReader $graph, array $case): string
    {
        $profiles = $graph->out($case, 'runs_as');

        return $profiles ? $profiles[0]['key'] : 'default';
    }

    /**
     * @param GraphReader $graph
     * @param array $case
     * @return string e.g. "acl/acl-x GET /api/v1/idea as aclNoProject [status, shape] {projectId}"
     */
    private function describe(GraphReader $graph, array $case): string
    {
        $attributes = $case['attributes'];
        $assertions = array_map(function ($node) {
            return $node['key'];
        }, $graph->out($case, 'asserts'));

        return $case['key'] . ' ' . $attributes['method'] . ' ' . $attributes['path'] . ' as '
            . $this->profile($graph, $case) . ' [' . implode(', ', $assertions) . ']'
            . ($attributes['placeholders'] ? ' {' . implode(', ', $attributes['placeholders']) . '}' : '')
            . ($attributes['enabled'] ? '' : ' (disabled)');
    }
}
