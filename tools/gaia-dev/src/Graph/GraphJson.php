<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph;

/**
 * Serialises a Graph to the committed `docs/generated/graph.json` format:
 * pretty-printed, sorted, LF line endings and no timestamps, so the same
 * sources always give the same bytes.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class GraphJson
{
    /**
     * @param Graph $graph
     * @return string
     */
    public static function encode(Graph $graph): string
    {
        $data = $graph->toArray();
        foreach ($data['nodes'] as $i => $node) {
            // Empty attributes stay a JSON object, not [].
            $data['nodes'][$i]['attributes'] = (object) $node['attributes'];
        }

        $document = [
            'schemaVersion' => Schema::VERSION,
            'generator' => 'tools/gaia-dev/bin/gaia-dev graph export',
            'nodes' => $data['nodes'],
            'edges' => $data['edges'],
        ];

        $json = json_encode(
            $document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($json === false) {
            throw new \RuntimeException('Cannot encode the graph: ' . json_last_error_msg());
        }

        return self::indent($json) . "\n";
    }

    /**
     * Re-indent json_encode's four spaces to two, keeping the file small.
     *
     * @param string $json
     * @return string
     */
    private static function indent(string $json): string
    {
        return preg_replace_callback('/^( {4})+/m', function ($matches) {
            return str_repeat('  ', strlen($matches[0]) / 4);
        }, $json);
    }
}
