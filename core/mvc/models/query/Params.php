<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

/**
 * Gives the read parameters of a model query their defaults, once, where they enter the model.
 *
 * Callers pass only the keys they need (`read(['id' => $id])`), so the query, clause, relationship and meta classes
 * would otherwise read undefined keys and loop over null. A missing scalar key defaults to null (what reading the
 * undefined key gave before) and a missing or null list key to an empty array (what looping over null did), so the
 * query built from the parameters does not change (agaia-4721).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Core\Mvc\Models\Query
 * @category Params
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Params
{
    /**
     * Scalar parameters: the where statement, sorting, grouping and having clause.
     *
     * @var array
     */
    const SCALAR_DEFAULTS = [
        'where' => null,
        'sort' => null,
        'order' => null,
        'groupBy' => null,
        'having' => null,
    ];

    /**
     * List parameters: the requested fields and relationships.
     *
     * @var array
     */
    const LIST_KEYS = ['fields', 'rels'];

    /**
     * Return the parameters with every missing key set to its default. Keys the caller gave keep their value,
     * except a null list, which becomes an empty array.
     *
     * @param  array $params The requested parameters.
     * @return array
     */
    public static function withDefaults(array $params)
    {
        $params += self::SCALAR_DEFAULTS;

        foreach (self::LIST_KEYS as $key) {
            if (!isset($params[$key])) {
                $params[$key] = [];
            }
        }

        return $params;
    }
}
