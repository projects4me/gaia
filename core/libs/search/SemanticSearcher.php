<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Exception\EmbeddingsUnavailableException;
use Gaia\Services\Embeddings;

/**
 * Matching by meaning: the query is embedded through Ares and compared to the type's searchEmbedding column by
 * cosine distance (the <=> operator, which the column's HNSW index serves), keeping the records within the maximum
 * distance, nearest first. A record without an embedding yet is not a candidate.
 *
 * The query is embedded once per search, for every type; when Ares cannot embed it the searcher is unavailable for
 * the whole search and does not ask again.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SemanticSearcher implements Searcher
{
    /**
     * The kind, as it appears in matchedBy.
     *
     * @var string
     */
    public const KIND = 'semantic';

    /**
     * The cosine distance of system.search.semanticMaxDistance when the config gives none.
     *
     * @var float
     */
    public const DEFAULT_MAX_DISTANCE = 0.5;

    /**
     * The hnsw.iterative_scan mode: strict_order keeps the records in exact distance order.
     *
     * @var string
     */
    private const ITERATIVE_SCAN = 'strict_order';

    /**
     * @var CandidateQuery
     */
    private $candidates;

    /**
     * @var Embeddings
     */
    private $embeddings;

    /**
     * @var float
     */
    private $maxDistance;

    /**
     * The query embedded so far, keyed by query text: a vector literal, or the failure.
     *
     * @var array
     */
    private $vectors = array();

    /**
     * @param CandidateQuery $candidates
     * @param Embeddings $embeddings
     * @param float $maxDistance cosine distance a match may have at most, from 0 to 2
     * @throws \Gaia\Exception\Exception When the distance is out of bounds
     */
    public function __construct(CandidateQuery $candidates, Embeddings $embeddings, $maxDistance)
    {
        if (!is_numeric($maxDistance) || !is_finite((float) $maxDistance) || $maxDistance < 0 || $maxDistance > 2) {
            throw new \Gaia\Exception\Exception('The semantic search maximum distance must be a number from 0 to 2.');
        }
        $this->candidates = $candidates;
        $this->embeddings = $embeddings;
        $this->maxDistance = (float) $maxDistance;
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
        return $target->block('semantic');
    }

    /**
     * {@inheritdoc}
     */
    public function rank(SearchTarget $target, $query, $limit)
    {
        if ($this->fields($target) === array()) {
            return array();
        }

        $column = $this->candidates->column(SearchBlock::EMBEDDING);
        $distance = $column . ' <=> CAST(:search_semantic AS vector)';
        $bind = array('search_semantic' => $this->vector($query), 'search_semantic_max' => $this->maxDistance);

        // The scope and the maximum distance filter the HNSW scan's candidates, which stop at hnsw.ef_search;
        // an iterative scan (pgvector 0.8) keeps scanning, nearest first, until the limit has records that pass,
        // so nearer records out of scope do not crowd out the ones in it (up to hnsw.max_scan_tuples)
        return $this->candidates->withSetting(
            'hnsw.iterative_scan',
            self::ITERATIVE_SCAN,
            function () use ($target, $column, $distance, $bind, $limit) {
                return $this->candidates->ids(
                    $target,
                    $column . ' IS NOT NULL AND ' . $distance . ' <= :search_semantic_max',
                    $distance,
                    $bind,
                    $limit
                );
            }
        );
    }

    /**
     * This function returns the query's embedding as a vector literal, embedding it on first use.
     *
     * @param string $query
     * @return string
     * @throws SearcherUnavailableException When Ares cannot embed the query
     */
    private function vector($query)
    {
        if (!isset($this->vectors[$query])) {
            try {
                $this->vectors[$query] = json_encode($this->embeddings->embedQuery($query));
            } catch (EmbeddingsUnavailableException $e) {
                $this->vectors[$query] = new SearcherUnavailableException(
                    'The query could not be embedded: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }
        if ($this->vectors[$query] instanceof SearcherUnavailableException) {
            throw $this->vectors[$query];
        }

        return $this->vectors[$query];
    }
}
