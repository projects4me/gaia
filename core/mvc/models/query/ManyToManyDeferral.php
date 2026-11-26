<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models\Query;

/**
 * Decides which requested hasManyToMany relationships a list reads in a query of its own, for the page's records,
 * instead of joining them, so their rows never multiply the records the limit counts (Tags D-002).
 *
 * A relationship is deferred when a filter on it needs no join (ManyToManyFilter::supports) and the list is a plain
 * one: not grouped, counted or filtered with having, not sorted by a field of the relationship, and not streamed.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Core\Mvc\Models\Query
 * @category ManyToManyDeferral
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ManyToManyDeferral
{
    /**
     * @var ManyToManyFilter
     */
    protected $filter;

    /**
     * @param ManyToManyFilter $filter
     */
    public function __construct(ManyToManyFilter $filter)
    {
        $this->filter = $filter;
    }

    /**
     * The requested relationships to read after the base query.
     *
     * @param  string $modelAlias Alias of the listed model.
     * @param  array  $params     Requested params.
     * @param  bool   $unbuffered Whether the list is streamed.
     * @return array Names of the relationships to read afterwards.
     */
    public function deferredRels($modelAlias, array $params, $unbuffered = false)
    {
        if ($unbuffered || !empty($params['groupBy']) || !empty($params['count']) || !empty($params['having'])
            || empty($params['rels']) || !is_array($params['rels'])
        ) {
            return [];
        }

        $sortedRels = $this->sortedRels($params['sort'] ?? null);
        $deferred = [];
        foreach ($params['rels'] as $relName) {
            if (!in_array($relName, $sortedRels, true) && $this->filter->supports($modelAlias, $relName)) {
                $deferred[] = $relName;
            }
        }

        return $deferred;
    }

    /**
     * The params without the given relationships and the fields requested from them.
     *
     * @param  array $params
     * @param  array $relNames
     * @return array
     */
    public function withoutRels(array $params, array $relNames)
    {
        if (empty($relNames)) {
            return $params;
        }

        $params['rels'] = array_values(array_diff($params['rels'], $relNames));
        if (!empty($params['fields']) && is_array($params['fields'])) {
            $params['fields'] = array_values(array_filter($params['fields'], function ($field) use ($relNames) {
                return !in_array($this->qualifier($field), $relNames, true);
            }));
        }

        return $params;
    }

    /**
     * Ids of the records in a base model result set.
     *
     * @param  \Phalcon\Mvc\Model\ResultsetInterface $resultSet
     * @param  string                                $modelAlias Alias of the listed model.
     * @return array
     */
    public function extractBaseModelIds($resultSet, $modelAlias)
    {
        $resultSet->setHydrateMode(\Phalcon\Mvc\Model\Resultset::HYDRATE_ARRAYS);
        $ids = [];
        foreach ($resultSet as $row) {
            $id = $row['id'] ?? ($row[$modelAlias]['id'] ?? null);
            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * The relationships a sort reads, from tokens such as `tag.tag`, `-tag.tag` or `tag.tag DESC`.
     *
     * @param  string|array|null $sort
     * @return array
     */
    protected function sortedRels($sort)
    {
        $tokens = is_array($sort) ? $sort : explode(',', (string) $sort);
        $rels = [];
        foreach ($tokens as $token) {
            $qualifier = $this->qualifier(ltrim(trim((string) $token), '+-'));
            if ($qualifier !== null) {
                $rels[] = $qualifier;
            }
        }

        return $rels;
    }

    /**
     * The alias a field is qualified with, e.g. `tag` for `tag.tag`; null for an unqualified field.
     *
     * @param  string $field
     * @return string|null
     */
    protected function qualifier($field)
    {
        $qualifier = strstr((string) $field, '.', true);

        return ($qualifier === false || $qualifier === '') ? null : $qualifier;
    }
}
