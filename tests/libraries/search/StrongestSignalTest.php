<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Search;

use Gaia\Libraries\Search\StrongestSignal;
use PHPUnit\Framework\TestCase;

/**
 * Ranking by the strongest signal on a 0-1 scale, with a threshold (agaia-4888), over fixed signals.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class StrongestSignalTest extends TestCase
{
    public function testARecordScoresItsStrongestSignal()
    {
        $ranked = $this->ranking()->rank(array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.3, 'issue:b' => 0.5)),
            array('kind' => 'fuzzy', 'signals' => array('issue:a' => 1.0)),
        ), 20);

        $this->assertSame(array('issue:a', 'issue:b'), array_column($ranked, 'key'));
        $this->assertEqualsWithDelta(0.6, $ranked[0]['score'], 1e-12);
        $this->assertSame(array('fts', 'fuzzy'), $ranked[0]['matchedBy']);
        $this->assertEqualsWithDelta(0.5, $ranked[1]['score'], 1e-12);
        $this->assertSame(array('fts'), $ranked[1]['matchedBy']);
    }

    public function testFuzzyCountsFromItsThresholdTimesItsWeight()
    {
        $ranked = $this->ranking(0)->rank(array(
            array('kind' => 'fuzzy', 'signals' => array('issue:a' => 0.3, 'issue:b' => 0.65, 'issue:c' => 1.0)),
        ), 20);

        $this->assertSame(
            array('issue:c' => 0.6, 'issue:b' => 0.3, 'issue:a' => 0.0),
            array_map(function ($score) {
                return round($score, 12);
            }, array_column($ranked, 'score', 'key'))
        );
    }

    public function testMeaningIsLinearBetweenNearAndFar()
    {
        $ranked = $this->ranking(0)->rank(array(
            array('kind' => 'semantic', 'signals' => array(
                'issue:near' => 0.1,
                'issue:mid' => 0.279,
                'issue:far' => 0.45,
            )),
        ), 20);

        $scores = array_column($ranked, 'score', 'key');
        $this->assertEqualsWithDelta(1.0, $scores['issue:near'], 1e-12);
        $this->assertEqualsWithDelta(0.5, $scores['issue:mid'], 1e-12);
        $this->assertEqualsWithDelta(0.0, $scores['issue:far'], 1e-12);
    }

    public function testDropsRecordsUnderTheThreshold()
    {
        $ranked = $this->ranking()->rank(array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.15, 'issue:b' => 0.1499)),
            array('kind' => 'fuzzy', 'signals' => array('issue:c' => 0.35)),
        ), 20);

        $this->assertSame(array('issue:a'), array_column($ranked, 'key'));
    }

    public function testExactMatchesScoreOneAndComeFirst()
    {
        $ranked = $this->ranking()->rank(array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.9, 'issue:b' => 0.4)),
            array('kind' => 'exact', 'signals' => array('issue:b' => 1.0, 'issue:c' => 1.0)),
        ), 20);

        $this->assertSame(array('issue:b', 'issue:c', 'issue:a'), array_column($ranked, 'key'));
        $this->assertSame(array(1.0, 1.0, 0.9), array_column($ranked, 'score'));
        $this->assertSame(array('fts', 'exact'), $ranked[0]['matchedBy']);
    }

    public function testAnExactMatchIsKeptWhateverTheThreshold()
    {
        $ranked = (new StrongestSignal(1, 0.6, 0.3, 0.156, 0.402))->rank(array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.99)),
            array('kind' => 'exact', 'signals' => array('issue:b' => 1.0)),
        ), 20);

        $this->assertSame(array('issue:b'), array_column($ranked, 'key'));
    }

    public function testEqualScoresKeepTheOrderOfTheirKeysAndTheLimitCuts()
    {
        $ranked = $this->ranking()->rank(array(
            array('kind' => 'fts', 'signals' => array('issue:c' => 0.5, 'issue:a' => 0.5, 'issue:b' => 0.5)),
        ), 2);

        $this->assertSame(array('issue:a', 'issue:b'), array_column($ranked, 'key'));
    }

    public function testNothingFoundRanksToNothing()
    {
        $this->assertSame(array(), $this->ranking()->rank(array(), 20));
    }

    public function testRejectsAKindWithoutAScale()
    {
        $this->expectException(\Gaia\Exception\Exception::class);
        $this->ranking()->rank(array(array('kind' => 'psychic', 'signals' => array('issue:a' => 1.0))), 20);
    }

    /**
     * @dataProvider badSettings
     */
    public function testRejectsASettingOutOfBounds(array $settings)
    {
        $this->expectException(\Gaia\Exception\Exception::class);
        new StrongestSignal(...$settings);
    }

    /**
     * @return array
     */
    public static function badSettings()
    {
        return array(
            'negative threshold' => array(array(-0.1, 0.6, 0.3, 0.156, 0.402)),
            'threshold past 1' => array(array(1.1, 0.6, 0.3, 0.156, 0.402)),
            'fuzzy weight not a number' => array(array(0.15, 'heavy', 0.3, 0.156, 0.402)),
            'fuzzy threshold past 1' => array(array(0.15, 0.6, 1.5, 0.156, 0.402)),
            'semantic near not finite' => array(array(0.15, 0.6, 0.3, NAN, 0.402)),
            'semantic far past 2' => array(array(0.15, 0.6, 0.3, 0.156, 2.1)),
            'far not past near' => array(array(0.15, 0.6, 0.3, 0.4, 0.4)),
        );
    }

    /**
     * @param float $threshold
     * @return StrongestSignal
     */
    private function ranking($threshold = StrongestSignal::DEFAULT_THRESHOLD)
    {
        return new StrongestSignal(
            $threshold,
            StrongestSignal::DEFAULT_FUZZY_WEIGHT,
            0.3,
            0.156,
            0.402
        );
    }
}
