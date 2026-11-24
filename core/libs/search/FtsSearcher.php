<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Full text search: matches the query as a tsquery against the type's searchVector and ranks by ts_rank_cd, normalized to 0-1, which is its signal.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FtsSearcher implements Searcher
{
    /**
     * The kind, as it appears in matchedBy.
     *
     * @var string
     */
    public const KIND = 'fts';

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
        return array_keys($target->block('fts'));
    }

    /**
     * {@inheritdoc}
     */
    public function rank(SearchTarget $target, SearchQuery $query, $limit)
    {
        $vector = $this->candidates->column(SearchBlock::VECTOR);
        $tsquery = $this->tsquery->sql();

        // Normalization 32 divides the rank by itself plus one, so the signal is from 0 to 1
        $rank = 'ts_rank_cd(' . $vector . ', ' . $tsquery . ', 32)';

        return $this->candidates->signals(
            $target,
            $vector . ' @@ ' . $tsquery,
            $rank,
            $rank . ' DESC',
            $this->tsquery->bind($target, $query->text()),
            $limit
        );
    }
}
