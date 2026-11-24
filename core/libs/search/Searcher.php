<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * One kind of matching (exact, fts, fuzzy, semantic): ranks the records of a searchable type against the query.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface Searcher
{
    /**
     * This function returns the kind, as it appears in a result's matchedBy.
     *
     * @return string
     */
    public function kind();

    /**
     * This function returns the fields the searcher matches on for a type; the search skips it when the user may not
     * read one of them, and when there are none.
     *
     * @param SearchTarget $target
     * @return array
     */
    public function fields(SearchTarget $target);

    /**
     * This function returns the best matching records in scope with the raw signal of each match (what it
     * means depends on the kind), best first.
     *
     * @param SearchTarget $target
     * @param SearchQuery $query
     * @param int $limit The most records to return
     * @return array Each id => its signal as a float, best first
     * @throws SearcherUnavailableException When a service it needs is down; the search goes on without it
     */
    public function rank(SearchTarget $target, SearchQuery $query, $limit);
}
