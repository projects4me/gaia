<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Query;

/**
 * Read access to an exported graph (docs/generated/graph.json): nodes by type
 * and key, edges followed in either direction, and name resolution with
 * "did you mean" suggestions. The queries share it so none of them walks the
 * raw arrays.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class GraphReader
{
    /**
     * @var array[] node id => node
     */
    private $nodes = [];

    /**
     * @var array[] node type => [key => node]
     */
    private $byType = [];

    /**
     * @var string[][] "<from id>\n<edge type>" => target ids
     */
    private $out = [];

    /**
     * @var string[][] "<to id>\n<edge type>" => source ids
     */
    private $in = [];

    /**
     * @param array $document the decoded graph.json
     */
    public function __construct(array $document)
    {
        foreach ($document['nodes'] as $node) {
            $this->nodes[$node['id']] = $node;
            $this->byType[$node['type']][$node['key']] = $node;
        }
        foreach ($document['edges'] as $edge) {
            $this->out[$edge['from'] . "\n" . $edge['type']][] = $edge['to'];
            $this->in[$edge['to'] . "\n" . $edge['type']][] = $edge['from'];
        }
    }

    /**
     * @param string $path
     * @return GraphReader
     * @throws QueryException when the file is missing or not a graph
     */
    public static function fromFile(string $path): GraphReader
    {
        if (!is_file($path)) {
            throw new QueryException(
                'No graph at ' . $path . '; run tools/gaia-dev/bin/gaia-dev graph export first'
            );
        }
        $document = json_decode(file_get_contents($path), true);
        if (
            !is_array($document)
            || !isset($document['nodes'], $document['edges'])
            || !is_array($document['nodes'])
            || !is_array($document['edges'])
        ) {
            throw new QueryException($path . ' is not a gaia-dev graph; run gaia-dev graph export');
        }

        return new self($document);
    }

    /**
     * @param string $type
     * @return array[] key => node, in graph order
     */
    public function nodes(string $type): array
    {
        return isset($this->byType[$type]) ? $this->byType[$type] : [];
    }

    /**
     * @param string $type
     * @param string $key
     * @return array|null
     */
    public function node(string $type, string $key): ?array
    {
        return isset($this->byType[$type][$key]) ? $this->byType[$type][$key] : null;
    }

    /**
     * @param array $node
     * @param string $edgeType
     * @return array[] nodes the edges leaving $node point to
     */
    public function out(array $node, string $edgeType): array
    {
        return $this->resolve($this->out, $node['id'], $edgeType);
    }

    /**
     * @param array $node
     * @param string $edgeType
     * @return array[] nodes whose edges point to $node
     */
    public function in(array $node, string $edgeType): array
    {
        return $this->resolve($this->in, $node['id'], $edgeType);
    }

    /**
     * Finds a node by key, ignoring case.
     *
     * @param string $type
     * @param string $name
     * @return array
     * @throws QueryException with the nearest keys when the name is unknown
     */
    public function find(string $type, string $name): array
    {
        $node = $this->node($type, $name);
        if ($node !== null) {
            return $node;
        }
        foreach ($this->nodes($type) as $key => $candidate) {
            if (strcasecmp($key, $name) === 0) {
                return $candidate;
            }
        }

        throw new QueryException($this->unknown($type, $name, array_keys($this->nodes($type))));
    }

    /**
     * @param string $what what was looked up, e.g. "model"
     * @param string $name
     * @param string[] $known
     * @return string one-line message with the nearest known names
     */
    public function unknown(string $what, string $name, array $known): string
    {
        $scored = [];
        foreach ($known as $key) {
            $distance = levenshtein(strtolower($name), strtolower($key));
            if (stripos($key, $name) !== false || stripos($name, $key) !== false) {
                $distance = min($distance, 1);
            }
            if ($distance <= max(2, (int) (strlen($name) / 3))) {
                $scored[$key] = $distance;
            }
        }
        asort($scored);
        $near = array_slice(array_keys($scored), 0, 3);

        return "Unknown {$what} '{$name}'" . ($near ? '. Did you mean: ' . implode(', ', $near) . '?' : '');
    }

    /**
     * @param array $index
     * @param string $id
     * @param string $edgeType
     * @return array[]
     */
    private function resolve(array $index, string $id, string $edgeType): array
    {
        $nodes = [];
        foreach (isset($index[$id . "\n" . $edgeType]) ? $index[$id . "\n" . $edgeType] : [] as $other) {
            $nodes[] = $this->nodes[$other];
        }

        return $nodes;
    }
}
