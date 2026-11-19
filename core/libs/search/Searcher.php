<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * One kind of matching (exact, fts, fuzzy, later semantic): ranks the records of a searchable type against the query.
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
     * This function returns the ids of the best matching records in scope, best first.
     *
     * @param SearchTarget $target
     * @param string $query The query text
     * @param int $limit The most ids to return
     * @return array
     */
    public function rank(SearchTarget $target, $query, $limit);
}
