<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Finds the records whose integer exact field starts or ends with a number query of two digits or more (917 finds
 * 9170 and 8917), the number itself left out: ExactMatcher finds that one and pins it above these. Its raw signal is
 * the share of the number's digits the query matched, plus 1 when the number starts with the query, so
 * StrongestSignal can put those first; equal signals come newest (highest number) first. The number is compared as
 * text (CAST ... AS TEXT LIKE), inside the scope: an index would not serve the "ends with" half, and the scope keeps
 * the scan to the user's records.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PartialNumberMatcher implements Searcher
{
    /**
     * The kind, as it appears in matchedBy.
     *
     * @var string
     */
    public const KIND = 'number';

    /**
     * The fewest digits a partial number needs.
     *
     * @var int
     */
    private const MIN_DIGITS = 2;

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
        return self::KIND;
    }

    /**
     * This function returns the integer exact fields.
     *
     * {@inheritdoc}
     */
    public function fields(SearchTarget $target)
    {
        return array_values(array_filter($target->block('exact'), function ($field) use ($target) {
            return ExactMatcher::isInteger($target->field($field));
        }));
    }

    /**
     * {@inheritdoc}
     */
    public function rank(SearchTarget $target, SearchQuery $query, $limit)
    {
        $digits = $this->digits($query);
        if ($digits === null) {
            return array();
        }

        $signals = array();
        foreach ($this->fields($target) as $field) {
            // Each use of a parameter has its own name: PDO does not take a name twice
            $number = 'CAST(' . $this->candidates->column($field) . ' AS TEXT)';
            $share = 'CAST(:search_length AS FLOAT) / length(' . $number . ')';
            $found = $this->candidates->signals(
                $target,
                '(' . $number . ' LIKE :search_starts OR ' . $number . ' LIKE :search_ends)'
                    . ' AND ' . $number . ' <> :search_digits',
                'CASE WHEN ' . $number . ' LIKE :search_starts_signal THEN 1 ELSE 0 END + ' . $share,
                '"signal" DESC, ' . $this->candidates->column($field) . ' DESC',
                array(
                    'search_starts' => $digits . '%',
                    'search_starts_signal' => $digits . '%',
                    'search_ends' => '%' . $digits,
                    'search_digits' => $digits,
                    'search_length' => strlen($digits),
                ),
                $limit
            );
            foreach ($found as $id => $signal) {
                if (!isset($signals[$id]) || $signal > $signals[$id]) {
                    $signals[$id] = $signal;
                }
            }
        }

        return $signals;
    }

    /**
     * This function returns the digits of a number query, leading zeros left out, or null when the query is not a
     * number or is too short to match part of one.
     *
     * @param SearchQuery $query
     * @return string|null
     */
    private function digits(SearchQuery $query)
    {
        $number = $query->number();
        if ($number === null) {
            return null;
        }
        $digits = ltrim($number, '0');

        return strlen($digits) >= self::MIN_DIGITS ? $digits : null;
    }
}
