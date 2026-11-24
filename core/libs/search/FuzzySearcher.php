<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Typo tolerant search: pg_trgm word_similarity of the query to the type's fuzzy fields, keeping the records with a
 * field that reaches the threshold, ranked by their best similarity, which is the signal. The match is the <% operator, which the fuzzy
 * fields' gin_trgm indexes serve; it reads the threshold from pg_trgm.word_similarity_threshold, set before each
 * search. It says nothing for a query of stop words only or of fewer than MIN_LENGTH letters and digits, where
 * near spellings are noise.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FuzzySearcher implements Searcher
{
    /**
     * The kind, as it appears in matchedBy.
     *
     * @var string
     */
    public const KIND = 'fuzzy';

    /**
     * The fewest letters and digits, outside stop words, a query needs for fuzzy matching.
     *
     * @var int
     */
    public const MIN_LENGTH = 3;

    /**
     * @var CandidateQuery
     */
    private $candidates;

    /**
     * @var float
     */
    private $threshold;

    /**
     * @param CandidateQuery $candidates
     * @param float $threshold word_similarity a match needs, from 0 to 1
     * @throws \Gaia\Exception\Exception When the threshold is out of bounds
     */
    public function __construct(CandidateQuery $candidates, $threshold)
    {
        if (!is_numeric($threshold) || $threshold < 0 || $threshold > 1) {
            throw new \Gaia\Exception\Exception('The fuzzy search threshold must be a number from 0 to 1.');
        }
        $this->candidates = $candidates;
        $this->threshold = (float) $threshold;
    }

    /**
     * {@inheritdoc}
     */
    public function kind()
    {
        return self::KIND;
    }

    /**
     * {@inheritdoc}
     */
    public function fields(SearchTarget $target)
    {
        return $target->block('fuzzy');
    }

    /**
     * {@inheritdoc}
     */
    public function rank(SearchTarget $target, SearchQuery $query, $limit)
    {
        $fields = $this->fields($target);
        if ($fields === array() || $query->nonStopLength() < self::MIN_LENGTH) {
            return array();
        }

        $matches = array();
        $similarities = array();
        foreach ($fields as $field) {
            $column = $this->candidates->column($field);
            $matches[] = ':search_fuzzy <% ' . $column;
            $similarities[] = 'COALESCE(word_similarity(:search_fuzzy, ' . $column . '), 0)';
        }
        $score = count($similarities) === 1 ? $similarities[0] : 'GREATEST(' . implode(', ', $similarities) . ')';

        return $this->candidates->withSetting(
            'pg_trgm.word_similarity_threshold',
            $this->threshold,
            function () use ($target, $matches, $score, $query, $limit) {
                return $this->candidates->signals(
                    $target,
                    implode(' OR ', $matches),
                    $score,
                    $score . ' DESC',
                    array('search_fuzzy' => $query->text()),
                    $limit
                );
            }
        );
    }
}
