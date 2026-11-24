<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Finds the unknown words of a query: its words a typo can be told from (SearchQuery::typoCandidates) that occur in
 * none of the records the user may read, in the types searched. They are checked against the types' searchVector at
 * search time, one indexed EXISTS per word and type, inside the user's scope, so a word only in a record the user
 * cannot read is unknown to them. A query with an unknown word is likely a typo, so fuzzy matches are kept for it.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class UnknownWords
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
     * @param CandidateQuery $candidates
     * @param TextSearchQuery $tsquery
     */
    public function __construct(CandidateQuery $candidates, TextSearchQuery $tsquery)
    {
        $this->candidates = $candidates;
        $this->tsquery = $tsquery;
    }

    /**
     * This function returns the query's words that occur in no record of the given targets in scope.
     *
     * @param SearchTarget[] $targets The types to look in, those whose searchVector the user may read
     * @param SearchQuery $query
     * @return array
     */
    public function of(array $targets, SearchQuery $query)
    {
        $unknown = array();
        foreach ($query->typoCandidates() as $word) {
            if (!$this->occurs($targets, $word)) {
                $unknown[] = $word;
            }
        }

        return $unknown;
    }

    /**
     * This function tells whether the query has a word that occurs in no record of the given targets in scope.
     *
     * @param SearchTarget[] $targets
     * @param SearchQuery $query
     * @return bool
     */
    public function any(array $targets, SearchQuery $query)
    {
        foreach ($query->typoCandidates() as $word) {
            if (!$this->occurs($targets, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param SearchTarget[] $targets
     * @param string $word
     * @return bool
     */
    private function occurs(array $targets, $word)
    {
        $vector = $this->candidates->column(SearchBlock::VECTOR);
        foreach ($targets as $target) {
            $tsquery = $this->tsquery->expression($target, KeywordQuery::plain($word));
            if ($this->candidates->exists($target, $vector . ' @@ ' . $tsquery['sql'], $tsquery['bind'])) {
                return true;
            }
        }

        return false;
    }
}
