<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models;

/**
 * Decides which requested collection relationships (hasMany, hasManyToMany) are loaded in
 * their own query instead of being joined into the base model query.
 *
 * Joining several collection relationships into one query multiplies their rows (a cross
 * product): 6 members x 64 milestones x 7 issue types return about 2,700 rows for one record.
 * Loading all but one of them separately makes the row count the sum of the related rows.
 *
 * A relationship stays joined when the request filters or sorts on it, because then it has to
 * shape the base result. Requests with explicit fields, grouping, having or count keep the
 * single joined query, since their result shape depends on it.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation\Mvc
 * @category Model
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CollectionRelsPlanner
{
    /**
     * Relationship types that return many rows per base model.
     *
     * @var array
     */
    const COLLECTION_TYPES = ['hasMany', 'hasManyToMany'];

    /**
     * Request params that make the base query depend on every joined relationship.
     *
     * @var array
     */
    const JOINED_ONLY_PARAMS = ['fields', 'addRelFields', 'groupBy', 'having', 'count'];

    /**
     * Returns the requested relationships to load in their own query.
     *
     * @param  array $params            The read params (rels, fields, sort, ...).
     * @param  array $relationshipsMeta The model's relationship metadata, keyed by type then name.
     * @param  array $filteredRels      Names used in the where clause (Clause::getFilteredRels()).
     * @return array Relationship names, in request order.
     */
    public function separateRels(array $params, array $relationshipsMeta, array $filteredRels)
    {
        if (empty($params['rels']) || !is_array($params['rels'])) {
            return [];
        }

        foreach (self::JOINED_ONLY_PARAMS as $param) {
            if (!empty($params[$param])) {
                return [];
            }
        }

        $collectionRels = $this->collectionRels($params['rels'], $relationshipsMeta);
        if (count($collectionRels) < 2) {
            return [];
        }

        $sort = isset($params['sort']) ? (string) $params['sort'] : '';
        $joinedRels = [];
        foreach ($collectionRels as $relName) {
            if (in_array($relName, $filteredRels, true) || $this->isSortedOn($relName, $sort)) {
                $joinedRels[] = $relName;
            }
        }

        // Nothing has to stay joined: keep the first one joined so the base query still loads it.
        if (empty($joinedRels)) {
            $joinedRels[] = $collectionRels[0];
        }

        return array_values(array_diff($collectionRels, $joinedRels));
    }

    /**
     * Returns the requested relationships that are collections, in request order.
     *
     * @param  array $rels
     * @param  array $relationshipsMeta
     * @return array
     */
    protected function collectionRels(array $rels, array $relationshipsMeta)
    {
        $collectionRels = [];
        foreach ($rels as $relName) {
            foreach (self::COLLECTION_TYPES as $type) {
                if (isset($relationshipsMeta[$type][$relName])) {
                    $collectionRels[] = $relName;
                    break;
                }
            }
        }

        return array_values(array_unique($collectionRels));
    }

    /**
     * Whether the sort expression names a field of the relationship, e.g. "milestones.name".
     *
     * @param  string $relName
     * @param  string $sort
     * @return bool
     */
    protected function isSortedOn($relName, $sort)
    {
        return $sort !== '' && preg_match('/(^|[^A-Za-z0-9_])' . preg_quote($relName, '/') . '\./', $sort) === 1;
    }
}
