<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Finds the records whose display title is the query, with case and spacing ignored. It is a kind of exact match
 * (an exact title scores 1 and goes first), kept apart from ExactMatcher so a user who may not read the title still
 * finds a record by its number. Its signal is 1.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ExactTitleMatcher implements Searcher
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
     * {@inheritdoc}
     */
    public function fields(SearchTarget $target)
    {
        $display = $target->block('display');

        return isset($display['title']) ? array($display['title']) : array();
    }

    /**
     * {@inheritdoc}
     */
    public function rank(SearchTarget $target, SearchQuery $query, $limit)
    {
        $fields = $this->fields($target);
        if ($fields === array()) {
            return array();
        }

        $condition = $this->normalized($this->candidates->column($fields[0]))
            . ' = ' . $this->normalized(':search_title');

        return $this->candidates->signals(
            $target,
            $condition,
            '1',
            null,
            array('search_title' => $query->text()),
            $limit
        );
    }

    /**
     * This function returns the SQL that lowercases a text and collapses its spaces. The title and the query both
     * go through it, so one case mapping serves both sides.
     *
     * @param string $operand
     * @return string
     */
    private function normalized($operand)
    {
        // Unicode spaces are listed so the database locale cannot leave a no-break space out
        return "lower(btrim(regexp_replace(" . $operand
            . ", '[[:space:]   -     　]+', ' ', 'g')))";
    }
}
