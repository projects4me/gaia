<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph\Mapper;

use GaiaDev\Graph\Graph;
use GaiaDev\Graph\Schema;

/**
 * Describes each assertion node with what the api-tester code says about it:
 * where it is checked, its failure messages (its meaning) and, for `shape`,
 * the allowed values; plus one real example, the first enabled case of the
 * catalogs that uses it.
 *
 * Assertions only a catalog uses stay without `source`: the api-tester does not
 * know them.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph\Mapper
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AssertionMapper
{
    /**
     * @param Graph $graph
     * @param array[] $assertions AssertionExtractor records
     * @param array[] $catalogs CatalogExtractor records
     */
    public function map(Graph $graph, array $assertions, array $catalogs)
    {
        $examples = $this->examples($catalogs);

        foreach ($assertions as $assertion) {
            $attributes = [
                'source' => $assertion['source'],
                'line' => $assertion['line'],
                'messages' => $assertion['messages'],
                'values' => $assertion['values'],
            ];
            if (isset($examples[$assertion['name']])) {
                $attributes['example'] = $examples[$assertion['name']];
            }
            $graph->addNode(Schema::ASSERTION, $assertion['name'], $attributes);
        }
    }

    /**
     * @param array[] $catalogs
     * @return string[] assertion name => case key (`<catalog>/<id>`)
     */
    private function examples(array $catalogs): array
    {
        $examples = [];
        foreach ($catalogs as $catalog) {
            foreach ($catalog['cases'] as $case) {
                if (!$case['enabled']) {
                    continue;
                }
                foreach ($case['assertions'] as $name) {
                    $examples[$name] = $examples[$name] ?? $catalog['catalog'] . '/' . $case['id'];
                }
            }
        }

        return $examples;
    }
}
