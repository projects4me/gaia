<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Builds a query's keyword query for a type: the alternatives QueryExpander proposes, kept only when they occur in a
 * record of the type the user may read. They are checked against the type's searchVector at search time, inside the
 * user's scope through CandidateQuery, so a form only in a record the user cannot read is not used: a single word by
 * one indexed EXISTS, a split pair as a phrase that needs splitMinRecords records (a pair of short words can meet by
 * chance in one record).
 *
 * The keyword query is kept per type and query text, and each form is checked once per type, so the keyword search
 * and the snippets of one search, and a repeated word, check each form once. What is kept depends on the user's scope,
 * held in the targets, so an instance serves one user: SearchServiceFactory builds one per search service, which is
 * built per request. A failed lookup is a database error, as a failed keyword search is.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class QueryExpansion
{
    /**
     * @var CandidateQuery
     */
    private $candidates;

    /**
     * @var TextSearchQuery
     */
    private $tsquery;

    /**
     * @var QueryExpander
     */
    private $expander;

    /**
     * @var int
     */
    private $splitMinRecords;

    /**
     * @var KeywordQuery[]
     */
    private $built = array();

    /**
     * Whether each form occurs, by type and form.
     *
     * @var bool[]
     */
    private $occurring = array();

    /**
     * @param CandidateQuery $candidates
     * @param TextSearchQuery $tsquery
     * @param QueryExpander $expander
     * @param int $splitMinRecords Records a split pair must occur in to be kept, a whole number from 1
     * @throws \Gaia\Exception\Exception When it is out of bounds
     */
    public function __construct(CandidateQuery $candidates, TextSearchQuery $tsquery, QueryExpander $expander, $splitMinRecords)
    {
        if (!is_int($splitMinRecords) || $splitMinRecords < 1) {
            throw new \Gaia\Exception\Exception('The search records a split word needs must be a whole number from 1.');
        }
        $this->candidates = $candidates;
        $this->tsquery = $tsquery;
        $this->expander = $expander;
        $this->splitMinRecords = $splitMinRecords;
    }

    /**
     * This function returns the keyword query of a query for a type.
     *
     * @param SearchTarget $target
     * @param SearchQuery $query
     * @return KeywordQuery
     */
    public function of(SearchTarget $target, SearchQuery $query)
    {
        $key = $target->type() . "\0" . $query->text();
        if (!isset($this->built[$key])) {
            $kept = array_values(array_filter($this->expander->proposals($query), function ($alternative) use ($target) {
                return $this->occurs($target, $alternative['form']);
            }));
            $this->built[$key] = KeywordQuery::expanded($query, $kept);
        }

        return $this->built[$key];
    }

    /**
     * @param SearchTarget $target
     * @param array $form One word, or the two words of a split
     * @return bool
     */
    private function occurs(SearchTarget $target, array $form)
    {
        $key = $target->type() . "\0" . implode(' ', $form);
        if (!isset($this->occurring[$key])) {
            $this->occurring[$key] = $this->lookUp($target, $form);
        }

        return $this->occurring[$key];
    }

    /**
     * @param SearchTarget $target
     * @param array $form
     * @return bool
     */
    private function lookUp(SearchTarget $target, array $form)
    {
        $vector = $this->candidates->column(SearchBlock::VECTOR);
        if (count($form) === 1) {
            $tsquery = $this->tsquery->expression($target, KeywordQuery::plain($form[0]));

            return $this->candidates->exists($target, $vector . ' @@ ' . $tsquery['sql'], $tsquery['bind']);
        }
        $tsquery = $this->tsquery->phrase($target, implode(' ', $form));

        return $this->candidates->countUpTo(
            $target,
            $vector . ' @@ ' . $tsquery['sql'],
            $tsquery['bind'],
            $this->splitMinRecords
        ) >= $this->splitMinRecords;
    }
}
