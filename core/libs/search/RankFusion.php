<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

/**
 * Reciprocal Rank Fusion: merges the rankings of any number of searchers into one. An item scores the sum of
 * 1 / (k + rank) over the rankings it is in (rank from 1), so an item many searchers rank high wins without their
 * scores having to be comparable. Items a pinned kind (exact) found stay on top, in fused order.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RankFusion
{
    /**
     * The RRF constant: damps the weight of the very top ranks.
     *
     * @var int
     */
    public const K = 60;

    /**
     * @var array
     */
    private $pinnedKinds;

    /**
     * @param array $pinnedKinds Kinds whose items go first
     */
    public function __construct(array $pinnedKinds = array(ExactMatcher::KIND))
    {
        $this->pinnedKinds = $pinnedKinds;
    }

    /**
     * This function fuses rankings into one list, best first.
     *
     * @param array $rankings Each ['kind' => string, 'keys' => list of item keys, best first]
     * @return array Each ['key' => string, 'score' => float, 'matchedBy' => list of kinds]
     */
    public function fuse(array $rankings)
    {
        $items = array();
        foreach ($rankings as $ranking) {
            foreach (array_values($ranking['keys']) as $index => $key) {
                $key = (string) $key;
                if (!isset($items[$key])) {
                    $items[$key] = array('key' => $key, 'score' => 0.0, 'matchedBy' => array(), 'pinned' => false);
                }
                $items[$key]['score'] += 1 / (self::K + $index + 1);
                if (!in_array($ranking['kind'], $items[$key]['matchedBy'], true)) {
                    $items[$key]['matchedBy'][] = $ranking['kind'];
                }
                if (in_array($ranking['kind'], $this->pinnedKinds, true)) {
                    $items[$key]['pinned'] = true;
                }
            }
        }

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
        }, $items);
    }
}
