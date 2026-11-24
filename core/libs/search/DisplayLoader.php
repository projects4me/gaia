<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Loads what a result shows: the display title, as it is, and the snippet (see Snippet). The caller leaves out a
 * display field the user may not read.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DisplayLoader
{
    /**
     * @var CandidateQuery
     */
    private $candidates;

    /**
     * @var Snippet
     */
    private $snippet;

    /**
     * @param CandidateQuery $candidates
     * @param Snippet $snippet
     */
    public function __construct(CandidateQuery $candidates, Snippet $snippet)
    {
        $this->candidates = $candidates;
        $this->snippet = $snippet;
    }

    /**
     * This function returns the display values of records, keyed by id; a record out of scope has none.
     *
     * @param SearchTarget $target
     * @param array $ids
     * @param KeywordQuery $keywords The keyword query the snippet marks
     * @param array $display The display entries to load: title and/or snippet => field
     * @return array Each id => ['title' => ..., 'snippet' => ...], with the entries asked for
     */
    public function load(SearchTarget $target, array $ids, KeywordQuery $keywords, array $display)
    {
        $select = array();
        $bind = array();
        if (isset($display['title'])) {
            $select['title'] = $this->candidates->column($display['title']);
        }
        if (isset($display['snippet'])) {
            $headline = $this->snippet->expression($target, $this->candidates->column($display['snippet']), $keywords);
            $select['snippet'] = $headline['sql'];
            $bind += $headline['bind'];
        }

        $values = array();
        foreach ($this->candidates->rowsById($target, $ids, $select, $bind) as $id => $row) {
            unset($row['id']);
            if (array_key_exists('snippet', $row)) {
                $row['snippet'] = $this->snippet->toHtml($row['snippet']);
            }
            $values[$id] = $row;
        }

        return $values;
    }
}
