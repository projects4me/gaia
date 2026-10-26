<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Query;

/**
 * `gaia-dev assertions [name]`: the api-tester assertion vocabulary, each with the
 * message its failure prints and one real case that uses it.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AssertionsQuery implements Query
{
    /**
     * {@inheritdoc}
     */
    public function run(GraphReader $graph, array $arguments, array $options): array
    {
        $assertions = isset($arguments[0])
            ? [$graph->find('assertion', $arguments[0])]
            : $graph->nodes('assertion');

        $lines = [];
        foreach ($assertions as $assertion) {
            $attributes = $assertion['attributes'];
            $lines[] = $assertion['key']
                . (!empty($attributes['values']) ? ' (' . implode('|', $attributes['values']) . ')' : '')
                . (!empty($attributes['messages']) ? ' -- ' . $attributes['messages'][0] : '')
                . (!empty($attributes['example']) ? ' | e.g. ' . $attributes['example'] : '');
        }

        return [
            'title' => count($lines) . ' assertions (expects[...] keys, mail.*, hermes.expect)',
            'assertions' => $lines,
        ];
    }
}
