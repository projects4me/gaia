<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Ranks the records the searchers found by their strongest signal on a 0-1 scale, so a score means how well a record
 * matched, not where a searcher put it:
 *
 * - fts: its normalized ts_rank_cd, as it is;
 * - fuzzy: fuzzyWeight x how far its similarity is past the fuzzy threshold (the threshold counts 0, a perfect match
 *   1), since a near spelling is weaker evidence than the words themselves;
 * - semantic: 1 at semanticNear or nearer, 0 at semanticFar or farther, linear in between;
 * - exact (the number, or the display title with case and spacing ignored): 1, first.
 *
 * A record scores the most of its signals; one under the threshold is dropped, unless exact. Exact records come
 * first and the rest by score, so scores never rise down the list; equal scores keep the order of their keys.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class StrongestSignal
{
    /**
     * The settings of system.search when the config gives none.
     *
     * @var float
     */
    public const DEFAULT_THRESHOLD = 0.15;
    public const DEFAULT_FUZZY_WEIGHT = 0.6;
    public const DEFAULT_SEMANTIC_NEAR = 0.156;
    public const DEFAULT_SEMANTIC_FAR = 0.402;

    /**
     * @var float
     */
    private $threshold;

    /**
     * @var float
     */
    private $fuzzyWeight;

    /**
     * @var float
     */
    private $fuzzyThreshold;

    /**
     * @var float
     */
    private $semanticNear;

    /**
     * @var float
     */
    private $semanticFar;

    /**
     * @param float $threshold The score a record needs to be kept, from 0 to 1
     * @param float $fuzzyWeight What a perfect fuzzy match scores, from 0 to 1
     * @param float $fuzzyThreshold The word_similarity the fuzzy searcher keeps from, from 0 to 1
     * @param float $semanticNear The cosine distance a meaning match scores 1 at, from 0 to 2
     * @param float $semanticFar The cosine distance a meaning match scores 0 at, from 0 to 2, past semanticNear
     * @throws \Gaia\Exception\Exception When a setting is out of bounds
     */
    public function __construct($threshold, $fuzzyWeight, $fuzzyThreshold, $semanticNear, $semanticFar)
    {
        $this->threshold = $this->bounded($threshold, 1, 'threshold');
        $this->fuzzyWeight = $this->bounded($fuzzyWeight, 1, 'fuzzy weight');
        $this->fuzzyThreshold = $this->bounded($fuzzyThreshold, 1, 'fuzzy threshold');
        $this->semanticNear = $this->bounded($semanticNear, 2, 'semantic near distance');
        $this->semanticFar = $this->bounded($semanticFar, 2, 'semantic far distance');
        if ($this->semanticFar <= $this->semanticNear) {
            throw new \Gaia\Exception\Exception(
                'The search semantic far distance must be past the semantic near distance.'
            );
        }
    }

    /**
     * This function ranks the searchers' signals into one list, best first.
     *
     * @param array $found Each ['kind' => string, 'signals' => item key => raw signal]
     * @param int $limit The most items to return
     * @return array Each ['key' => string, 'score' => float from 0 to 1, 'matchedBy' => list of kinds]
     * @throws \Gaia\Exception\Exception When a kind has no scale
     */
    public function rank(array $found, $limit)
    {
        $items = array();
        foreach ($found as $searcher) {
            foreach ($searcher['signals'] as $key => $signal) {
                $key = (string) $key;
                if (!isset($items[$key])) {
                    $items[$key] = array('key' => $key, 'score' => 0.0, 'matchedBy' => array(), 'pinned' => false);
                }
                $items[$key]['score'] = max($items[$key]['score'], $this->strength($searcher['kind'], $signal));
                if (!in_array($searcher['kind'], $items[$key]['matchedBy'], true)) {
                    $items[$key]['matchedBy'][] = $searcher['kind'];
                }
                if ($searcher['kind'] === ExactMatcher::KIND) {
                    $items[$key]['pinned'] = true;
                }
            }
        }

        $items = array_filter($items, function ($item) {
            return $item['pinned'] || $item['score'] >= $this->threshold;
        });
        usort($items, function ($a, $b) {
            if ($a['pinned'] !== $b['pinned']) {
                return $a['pinned'] ? -1 : 1;
            }
            if ($a['score'] !== $b['score']) {
                return $a['score'] > $b['score'] ? -1 : 1;
            }

            return strcmp($a['key'], $b['key']);
        });

        return array_map(function ($item) {
            unset($item['pinned']);

            return $item;
        }, array_slice($items, 0, $limit));
    }

    /**
     * This function puts a searcher's raw signal on the 0-1 scale.
     *
     * @param string $kind
     * @param float $signal
     * @return float
     * @throws \Gaia\Exception\Exception When the kind has no scale
     */
    private function strength($kind, $signal)
    {
        switch ($kind) {
            case ExactMatcher::KIND:
                return 1.0;
            case FtsSearcher::KIND:
                return $this->clamp($signal);
            case FuzzySearcher::KIND:
                $past = $this->fuzzyThreshold < 1
                    ? ($signal - $this->fuzzyThreshold) / (1 - $this->fuzzyThreshold)
                    : (float) ($signal >= 1);

                return $this->fuzzyWeight * $this->clamp($past);
            case SemanticSearcher::KIND:
                return $this->clamp(($this->semanticFar - $signal) / ($this->semanticFar - $this->semanticNear));
        }

        throw new \Gaia\Exception\Exception("Search signals of kind '{$kind}' have no scale.");
    }

    /**
     * @param float $value
     * @return float
     */
    private function clamp($value)
    {
        return min(1.0, max(0.0, (float) $value));
    }

    /**
     * @param mixed $value
     * @param int $max
     * @param string $name
     * @return float
     * @throws \Gaia\Exception\Exception When the value is not a number from 0 to max
     */
    private function bounded($value, $max, $name)
    {
        if (!is_numeric($value) || !is_finite((float) $value) || $value < 0 || $value > $max) {
            throw new \Gaia\Exception\Exception("The search {$name} must be a number from 0 to {$max}.");
        }

        return (float) $value;
    }
}
