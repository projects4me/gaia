<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Checks the shape of a workflow's graph. Nodes are
 * `['id', 'category', 'kind', 'config' => array]` and edges
 * `['id', 'fromNodeId', 'fromPort', 'toNodeId']`.
 *
 * `edgeErrors` checks one edge as it is saved: both ends exist, the port is
 * one of the source node's, the port is free, the target is not the trigger
 * and the edge closes no cycle. `errors` checks a whole graph before it runs:
 * exactly one trigger, every edge valid, every port connected (a path ends
 * explicitly at an `end` node), every other node reached, and no cycles.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowGraph
{
    /**
     * @var NodeKindCatalog
     */
    private $catalog;

    /**
     * @param NodeKindCatalog|null $catalog
     */
    public function __construct(NodeKindCatalog $catalog = null)
    {
        $this->catalog = $catalog ?: new NodeKindCatalog();
    }

    /**
     * The problems that keep the graph from running, empty when it can run.
     *
     * @param  array $nodes
     * @param  array $edges
     * @return string[]
     */
    public function errors(array $nodes, array $edges)
    {
        $byId = $this->index($nodes);
        $errors = [];

        $triggers = $this->triggerCount($nodes);
        if ($triggers !== 1) {
            $errors[] = "A workflow needs exactly one trigger; it has {$triggers}.";
        }

        $edges = array_values($edges);
        foreach ($edges as $i => $edge) {
            $others = $edges;
            unset($others[$i]);
            foreach ($this->edgeShapeErrors($byId, $others, $edge) as $error) {
                $errors[] = $error;
            }
        }

        $used = [];
        $reached = [];
        foreach ($edges as $edge) {
            $used[$edge['fromNodeId']][$edge['fromPort']] = true;
            $reached[$edge['toNodeId']] = true;
        }
        foreach ($byId as $id => $node) {
            $name = $this->label($node);
            foreach ($this->ports($node) as $port) {
                if (!isset($used[$id][$port])) {
                    $errors[] = "Port '{$port}' of {$name} is not connected; connect it or end it with an end node.";
                }
            }
            if ($node['category'] !== NodeKindCatalog::TRIGGER && !isset($reached[$id])) {
                $errors[] = "{$name} is not reached from the trigger.";
            }
        }

        if ($this->hasCycle($edges)) {
            $errors[] = 'A workflow cannot loop back on itself.';
        }

        return array_values(array_unique($errors));
    }

    /**
     * The problems with saving one edge next to the other edges of its
     * workflow (the edge itself left out), empty when it can be saved.
     *
     * @param  array $nodes
     * @param  array $otherEdges
     * @param  array $edge
     * @return string[]
     */
    public function edgeErrors(array $nodes, array $otherEdges, array $edge)
    {
        $errors = $this->edgeShapeErrors($this->index($nodes), $otherEdges, $edge);
        if ($errors === [] && $this->hasCycle(array_merge($otherEdges, [$edge]))) {
            $errors[] = 'This edge would make the workflow loop back on itself.';
        }

        return $errors;
    }

    /**
     * How many trigger nodes the graph has.
     *
     * @param  array $nodes
     * @return int
     */
    public function triggerCount(array $nodes)
    {
        return count(array_filter($nodes, function ($node) {
            return $node['category'] === NodeKindCatalog::TRIGGER;
        }));
    }

    /**
     * @param  array $byId
     * @param  array $otherEdges
     * @param  array $edge
     * @return string[]
     */
    private function edgeShapeErrors(array $byId, array $otherEdges, array $edge)
    {
        $from = isset($byId[$edge['fromNodeId']]) ? $byId[$edge['fromNodeId']] : null;
        $to = isset($byId[$edge['toNodeId']]) ? $byId[$edge['toNodeId']] : null;
        if ($from === null || $to === null) {
            return ['An edge must join two nodes of the same workflow.'];
        }
        if ($edge['fromNodeId'] === $edge['toNodeId']) {
            return ['An edge cannot join a node to itself.'];
        }

        $errors = [];
        $ports = $this->ports($from);
        if (!in_array($edge['fromPort'], $ports, true)) {
            $errors[] = "'{$edge['fromPort']}' is not a port of {$this->label($from)}"
                . ($ports === [] ? '; it has none.' : ' (' . implode(', ', $ports) . ').');
        }
        foreach ($otherEdges as $other) {
            if ($other['fromNodeId'] === $edge['fromNodeId'] && $other['fromPort'] === $edge['fromPort']) {
                $errors[] = "Port '{$edge['fromPort']}' of {$this->label($from)} is already connected.";
                break;
            }
        }
        if ($to['category'] === NodeKindCatalog::TRIGGER) {
            $errors[] = 'An edge cannot lead into the trigger.';
        }

        return $errors;
    }

    /**
     * Whether the edges contain a directed cycle (depth-first search).
     *
     * @param  array $edges
     * @return bool
     */
    private function hasCycle(array $edges)
    {
        $next = [];
        foreach ($edges as $edge) {
            $next[$edge['fromNodeId']][] = $edge['toNodeId'];
        }

        $state = [];
        $visit = function ($id) use (&$visit, &$state, $next) {
            $state[$id] = 'open';
            foreach (isset($next[$id]) ? $next[$id] : [] as $to) {
                $seen = isset($state[$to]) ? $state[$to] : null;
                if ($seen === 'open' || ($seen === null && $visit($to))) {
                    return true;
                }
            }
            $state[$id] = 'done';
            return false;
        };

        foreach (array_keys($next) as $id) {
            if (!isset($state[$id]) && $visit($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array $node
     * @return string[]
     */
    private function ports(array $node)
    {
        if (!$this->catalog->has($node['category'], $node['kind'])) {
            return [];
        }

        return $this->catalog->ports($node['category'], $node['kind'], $node['config']);
    }

    /**
     * @param  array $nodes
     * @return array
     */
    private function index(array $nodes)
    {
        $byId = [];
        foreach ($nodes as $node) {
            $byId[$node['id']] = $node;
        }

        return $byId;
    }

    /**
     * @param  array $node
     * @return string
     */
    private function label(array $node)
    {
        $name = isset($node['name']) && $node['name'] !== null && $node['name'] !== '' ? $node['name'] : $node['kind'];
        return "node '{$name}'";
    }
}
