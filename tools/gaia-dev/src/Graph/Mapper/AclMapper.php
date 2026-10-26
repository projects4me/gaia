<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph\Mapper;

use GaiaDev\Graph\Graph;
use GaiaDev\Graph\Schema;

/**
 * Maps the ACL rules onto permission nodes and links them to what they protect.
 *
 * - Every action of a REST controller `requires` the permission
 *   `<controller>.<permission action>` (`issue.post` requires `issue.create`).
 * - A permission named `<model>.<field>.<action>` `guards` that model's field.
 * - Scoped resources (record scope none / all / members) carry the scope values.
 *
 * Run it after RouteMapper, ModelMapper and ProfileMapper, so the actions, the
 * fields and the permissions the profiles hold exist.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph\Mapper
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AclMapper
{
    /**
     * @param Graph $graph
     * @param array $acl AclExtractor record
     */
    public function map(Graph $graph, array $acl)
    {
        foreach ($acl['scopedResources'] as $resource) {
            $graph->addNode(Schema::PERMISSION, $resource, ['scoped' => true, 'scopes' => $acl['scopes']]);
        }

        $this->linkActions($graph, $acl['actions']);
        $this->linkFields($graph);

        foreach ($graph->nodesOfType(Schema::PERMISSION) as $id => $permission) {
            if (!isset($permission['attributes']['kind'])) {
                $graph->addNode(Schema::PERMISSION, $permission['key'], ['kind' => 'action']);
            }
            if (!isset($permission['attributes']['scoped'])) {
                $graph->addNode(Schema::PERMISSION, $permission['key'], ['scoped' => false]);
            }
        }
    }

    /**
     * @param Graph $graph
     * @param string[] $permissionActions REST action => permission action
     */
    private function linkActions(Graph $graph, array $permissionActions)
    {
        $restControllers = [];
        foreach ($graph->nodesOfType(Schema::CONTROLLER) as $controller) {
            if (!empty($controller['attributes']['rest'])) {
                $restControllers[$controller['key']] = true;
            }
        }

        foreach ($graph->nodesOfType(Schema::ACTION) as $actionId => $action) {
            list($controller, $name) = explode('.', $action['key'], 2);
            if (!isset($restControllers[$controller]) || !isset($permissionActions[$name])) {
                continue;
            }
            $permissionId = $graph->addNode(
                Schema::PERMISSION,
                $controller . '.' . $permissionActions[$name],
                ['kind' => 'action']
            );
            $graph->addEdge($actionId, 'requires', $permissionId);
        }
    }

    /**
     * @param Graph $graph
     */
    private function linkFields(Graph $graph)
    {
        $fields = [];
        foreach ($graph->nodesOfType(Schema::FIELD) as $id => $field) {
            list($model, $name) = explode('.', $field['key'], 2);
            $fields[strtolower($model) . '.' . $name] = $id;
        }

        foreach ($graph->nodesOfType(Schema::PERMISSION) as $id => $permission) {
            $parts = explode('.', $permission['key']);
            if (count($parts) !== 3) {
                continue;
            }
            $fieldKey = $parts[0] . '.' . $parts[1];
            if (isset($fields[$fieldKey])) {
                $graph->addNode(Schema::PERMISSION, $permission['key'], ['kind' => 'field']);
                $graph->addEdge($id, 'guards', $fields[$fieldKey]);
            }
        }
    }
}
