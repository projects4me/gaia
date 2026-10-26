<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Query;

/**
 * `gaia-dev model <Name>`: table, fields, relationships, behaviours, ACL, routes,
 * controller, components and the api-tester cases that touch the model.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ModelQuery implements Query
{
    /**
     * Audit and housekeeping fields every model has; shown as one line.
     */
    private const COMMON_FIELDS = [
        'id', 'deleted', 'dateCreated', 'dateModified', 'createdUser', 'createdUserName', 'modifiedUser',
        'modifiedUserName',
    ];

    /**
     * {@inheritdoc}
     */
    public function run(GraphReader $graph, array $arguments, array $options): array
    {
        if (!isset($arguments[0])) {
            throw new QueryException('Usage: gaia-dev model <Name>');
        }
        $model = $graph->find('model', $arguments[0]);
        $attributes = $model['attributes'];

        $fields = [];
        $common = [];
        foreach ($graph->out($model, 'has_field') as $field) {
            $name = substr($field['key'], strlen($model['key']) + 1);
            if (in_array($name, self::COMMON_FIELDS, true)) {
                $common[] = $name;
            } else {
                $fields[] = $this->describeField($name, $field['attributes']);
            }
        }
        if ($common) {
            $fields[] = 'also: ' . implode(', ', $common);
        }

        $relationships = [];
        foreach ($graph->out($model, 'has_relationship') as $relationship) {
            $via = $this->keys($graph->out($relationship, 'via'));
            $relationships[] = substr($relationship['key'], strlen($model['key']) + 1) . ' '
                . $relationship['attributes']['kind'] . ' -> ' . implode(',', $this->keys($graph->out($relationship, 'targets')))
                . ($via ? ' via ' . implode(',', $via) : '');
        }

        $answer = [
            'title' => 'Model ' . $model['key'] . ' (' . $this->describeSource($attributes)
                . ($attributes['registered'] ? ', registered' : ', not registered') . ')',
            'fields' => $fields,
            'relationships' => $relationships,
            'behaviours' => implode(', ', $this->keys($graph->out($model, 'uses_behaviour'))) ?: '-',
            'acl' => !empty($attributes['acl']) ? $this->describeAcl($attributes['acl']) : '-',
        ];

        foreach ($graph->in($model, 'serves') as $controller) {
            $answer['controller'] = $controller['attributes']['class'];
            $answer['routes'] = $this->routes($graph, $controller);
            $answer['components'] = implode(', ', $this->keys($graph->out($controller, 'uses_component'))) ?: '-';
            $answer['cases'] = $this->cases($graph, $controller);
        }

        return $answer;
    }

    /**
     * @param array $attributes the model node's attributes
     * @return string e.g. "table issues", or "no metadata" for a model without a metadata file
     */
    private function describeSource(array $attributes): string
    {
        if (!isset($attributes['tableName'])) {
            return 'no metadata';
        }

        return ($attributes['isView'] ? 'view ' : 'table ') . $attributes['tableName'];
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
     * @param string $name
     * @param array $attributes
     * @return string e.g. "title varchar(200) required"
     */
    private function describeField(string $name, array $attributes): string
    {
        $type = isset($attributes['type']) ? $attributes['type'] : '?';
        $flags = [];
        if (isset($attributes['length'])) {
            $type .= '(' . $attributes['length'] . ')';
        }
        if (isset($attributes['null']) && $attributes['null'] === false) {
            $flags[] = 'required';
        }
        foreach (['identifier', 'secure'] as $flag) {
            if (!empty($attributes[$flag])) {
                $flags[] = $flag;
            }
        }
        if (!empty($attributes['acl'])) {
            $flags[] = 'acl';
        }

        return trim($name . ' ' . $type . ($flags ? ' ' . implode(', ', $flags) : ''));
    }

    /**
     * @param array $acl the model's acl block
     * @return string
     */
    private function describeAcl(array $acl): string
    {
        $parts = [];
        foreach (isset($acl['groups']) ? $acl['groups'] : [] as $group) {
            $key = isset($acl['groupKeys'][$group]) ? $acl['groupKeys'][$group] : '?';
            $parts[] = "group {$group} via {$key}";
        }
        foreach ($acl as $name => $value) {
            if (!in_array($name, ['groups', 'groupKeys'], true)) {
                $parts[] = $name . '=' . json_encode($value, JSON_UNESCAPED_SLASHES);
            }
        }

        return implode('; ', $parts);
    }

    /**
     * @param GraphReader $graph
     * @param array $controller
     * @return string
     */
    private function routes(GraphReader $graph, array $controller): string
    {
        $routes = [];
        foreach ($graph->out($controller, 'has_action') as $action) {
            foreach ($graph->in($action, 'handled_by') as $route) {
                $routes[] = $route['attributes']['method'] . ' ' . $route['attributes']['path'];
            }
        }
        sort($routes);

        return implode(', ', $routes) ?: '-';
    }

    /**
     * @param GraphReader $graph
     * @param array $controller
     * @return string
     */
    private function cases(GraphReader $graph, array $controller): string
    {
        $counts = [];
        foreach ($graph->in($controller, 'covers') as $case) {
            $catalog = $case['attributes']['catalog'];
            $counts[$catalog] = (isset($counts[$catalog]) ? $counts[$catalog] : 0) + 1;
        }
        ksort($counts);
        $parts = [];
        foreach ($counts as $catalog => $count) {
            $parts[] = "{$catalog} {$count}";
        }

        return $parts
            ? array_sum($counts) . ' (' . implode(', ', $parts) . '); gaia-dev cases ' . $controller['key']
            : 'none';
    }
}
