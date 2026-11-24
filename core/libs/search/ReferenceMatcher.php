<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Finds the record a reference query names (agaia-4170): its reference prefix field (the search block's
 * referencePrefix, e.g. the project's short code) equals the prefix, with case ignored, and an integer exact field
 * equals the number. It is a kind of exact match (score 1, first). Whether a prefix is known, so the query is a
 * reference and not plain text, is looked up in the records the user may see, so a prefix only other projects have
 * reads as plain text and tells nothing about them.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ReferenceMatcher implements Searcher
{
    /**
     * @var CandidateQuery
     */
    private $candidates;

    /**
     * @param CandidateQuery $candidates
     */
    public function __construct(CandidateQuery $candidates)
    {
        $this->candidates = $candidates;
    }

    /**
     * {@inheritdoc}
     */
    public function kind()
    {
        return ExactMatcher::KIND;
    }

    /**
     * This function returns the reference prefix field and the integer exact fields, or none when the type has no
     * reference prefix or no integer exact field.
     *
     * {@inheritdoc}
     */
    public function fields(SearchTarget $target)
    {
        $prefix = $target->block('referencePrefix');
        $numbers = $this->numberFields($target);
        if ($prefix === null || $numbers === array()) {
            return array();
        }

        return array_merge(array($prefix), $numbers);
    }

    /**
     * This function tells whether a record the user may see has the query's reference prefix.
     *
     * @param SearchTarget $target
     * @param SearchQuery $query
     * @return bool
     */
    public function knows(SearchTarget $target, SearchQuery $query)
    {
        $reference = $query->reference();
        if ($reference === null || $this->fields($target) === array()) {
            return false;
        }

        return $this->candidates->exists(
            $target,
            $this->prefixCondition($target),
            array('search_prefix' => $reference['prefix'])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function rank(SearchTarget $target, SearchQuery $query, $limit)
    {
        $reference = $query->reference();
        if ($reference === null || $this->fields($target) === array()) {
            return array();
        }

        $conditions = array();
        $bind = array('search_prefix' => $reference['prefix']);
        foreach ($this->numberFields($target) as $i => $field) {
            $value = ExactMatcher::integerValue($target->field($field), $reference['number']);
            if ($value === null) {
                continue;
            }
            $conditions[] = $this->candidates->column($field) . ' = :search_reference' . $i;
            $bind['search_reference' . $i] = $value;
        }
        if ($conditions === array()) {
            return array();
        }

        return $this->candidates->signals(
            $target,
            $this->prefixCondition($target) . ' AND (' . implode(' OR ', $conditions) . ')',
            '1',
            null,
            $bind,
            $limit
        );
    }

    /**
     * @param SearchTarget $target
     * @return string
     */
    private function prefixCondition(SearchTarget $target)
    {
        return 'lower(' . $this->candidates->column($target->block('referencePrefix')) . ') = lower(:search_prefix)';
    }

    /**
     * @param SearchTarget $target
     * @return array
     */
    private function numberFields(SearchTarget $target)
    {
        return array_values(array_filter($target->block('exact'), function ($field) use ($target) {
            return ExactMatcher::isInteger($target->field($field));
        }));
    }
}
