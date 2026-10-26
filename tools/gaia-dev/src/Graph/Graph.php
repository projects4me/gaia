<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph;

use InvalidArgumentException;

/**
 * Typed nodes and edges of the Gaia code graph, checked against the Schema.
 *
 * Adding a node that already exists merges its attributes, so several mappers
 * can describe the same node (e.g. a model named in models.php and in metadata).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Graph
{
    /**
     * @var array[] node id => node
     */
    private $nodes = [];

    /**
     * @var array[] edge key => edge
     */
    private $edges = [];

    /**
     * @param string $type a Schema node type
     * @param string $key unique within the type
     * @param array $attributes
     * @return string the node id
     */
    public function addNode(string $type, string $key, array $attributes = []): string
    {
        $id = Schema::nodeId($type, $key);

        if (isset($this->nodes[$id])) {
            $this->nodes[$id]['attributes'] = array_merge($this->nodes[$id]['attributes'], $attributes);
        } else {
            $this->nodes[$id] = ['id' => $id, 'type' => $type, 'key' => $key, 'attributes' => $attributes];
        }

        return $id;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function hasNode(string $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /**
     * @param string $type
     * @return array[] nodes of that type, keyed by id
     */
    public function nodesOfType(string $type): array
    {
        return array_filter($this->nodes, function ($node) use ($type) {
            return $node['type'] === $type;
        });
    }

    /**
     * Both ends must exist; duplicate edges are ignored.
     *
     * @param string $from node id
     * @param string $type a Schema edge type
     * @param string $to node id
     */
    public function addEdge(string $from, string $type, string $to)
    {
        foreach ([$from, $to] as $id) {
            if (!isset($this->nodes[$id])) {
                throw new InvalidArgumentException("Edge {$type} refers to a missing node: {$id}");
            }
        }
        Schema::assertEdge($type, $this->nodes[$from]['type'], $this->nodes[$to]['type']);

        $this->edges[$from . "\n" . $type . "\n" . $to] = ['from' => $from, 'type' => $type, 'to' => $to];
    }

    /**
     * Nodes sorted by id and edges by from, type and to, so the export is stable.
     *
     * @return array with the keys nodes and edges
     */
    public function toArray(): array
    {
        $nodes = $this->nodes;
        ksort($nodes, SORT_STRING);
        $edges = $this->edges;
        ksort($edges, SORT_STRING);

        return ['nodes' => array_values($nodes), 'edges' => array_values($edges)];
    }
}
