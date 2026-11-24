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
    /**
     * The all-words boundary the tests rank with.
     *
     * @var float
     */
    private const BOUNDARY = 0.5;

    public function testARecordScoresItsStrongestSignal()
    {
        $ranked = $this->ranking()->rank(array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.3, 'issue:b' => 0.5)),
            array('kind' => 'fuzzy', 'signals' => array('issue:a' => 1.0)),
        ), 20, true, false);

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
        ), 20, true, false);

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
        ), 20, true, false);

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
        ), 20, true, false);

        $this->assertSame(array('issue:a'), array_column($ranked, 'key'));
    }

    public function testExactMatchesScoreOneAndComeFirst()
    {
        $ranked = $this->ranking()->rank(array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.9, 'issue:b' => 0.4)),
            array('kind' => 'exact', 'signals' => array('issue:b' => 1.0, 'issue:c' => 1.0)),
        ), 20, true, false);

        $this->assertSame(array('issue:b', 'issue:c', 'issue:a'), array_column($ranked, 'key'));
        $this->assertSame(array(1.0, 1.0, 0.9), array_column($ranked, 'score'));
        $this->assertSame(array('fts', 'exact'), $ranked[0]['matchedBy']);
    }

    public function testAnExactMatchIsKeptWhateverTheThreshold()
    {
        $ranked = (new StrongestSignal(0.995, 0.6, 0.3, 0.156, 0.402, 0.998))->rank(array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.99)),
            array('kind' => 'exact', 'signals' => array('issue:b' => 1.0)),
        ), 20, true, false);

        $this->assertSame(array('issue:b'), array_column($ranked, 'key'));
    }

    public function testEqualScoresKeepTheOrderOfTheirKeysAndTheLimitCuts()
    {
        $ranked = $this->ranking()->rank(array(
            array('kind' => 'fts', 'signals' => array('issue:c' => 0.5, 'issue:a' => 0.5, 'issue:b' => 0.5)),
        ), 2, true, false);

        $this->assertSame(array('issue:a', 'issue:b'), array_column($ranked, 'key'));
    }

    public function testFuzzyOnlyRecordsAreDroppedWhenKeywordOrMeaningMatchesAndNoWordIsUnknown()
    {
        $found = array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.5)),
            array('kind' => 'fuzzy', 'signals' => array('issue:a' => 1.0, 'issue:b' => 1.0)),
        );

        $this->assertSame(array('issue:a'), array_column($this->ranking()->rank($found, 20, false, false), 'key'));
        $this->assertSame(array('issue:a', 'issue:b'), array_column($this->ranking()->rank($found, 20, true, false), 'key'));
    }

    public function testFuzzyOnlyRecordsStayWhenNothingElseMatches()
    {
        $found = array(array('kind' => 'fuzzy', 'signals' => array('issue:b' => 1.0)));

        $this->assertSame(array('issue:b'), array_column($this->ranking()->rank($found, 20, false, false), 'key'));
    }

    public function testAMeaningMatchUnderTheThresholdDoesNotDropFuzzyOnlyRecords()
    {
        $found = array(
            array('kind' => 'semantic', 'signals' => array('issue:a' => 0.4)),
            array('kind' => 'fuzzy', 'signals' => array('issue:b' => 1.0)),
        );

        $this->assertSame(array('issue:b'), array_column($this->ranking()->rank($found, 20, false, false), 'key'));
    }

    public function testAMeaningMatchAtTheThresholdDropsFuzzyOnlyRecords()
    {
        $found = array(
            array('kind' => 'semantic', 'signals' => array('issue:a' => 0.156)),
            array('kind' => 'fuzzy', 'signals' => array('issue:b' => 1.0)),
        );

        $this->assertSame(array('issue:a'), array_column($this->ranking()->rank($found, 20, false, false), 'key'));
    }

    public function testExactRecordsAreNeverDropped()
    {
        $found = array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.5)),
            array('kind' => 'exact', 'signals' => array('issue:e' => 1.0)),
            array('kind' => 'fuzzy', 'signals' => array('issue:e' => 1.0)),
        );

        $this->assertSame(array('issue:e', 'issue:a'), array_column($this->ranking()->rank($found, 20, false, false), 'key'));
    }

    public function testNothingFoundRanksToNothing()
    {
        $this->assertSame(array(), $this->ranking()->rank(array(), 20, true, false));
    }

    public function testRejectsAKindWithoutAScale()
    {
        $this->expectException(\Gaia\Exception\Exception::class);
        $this->ranking()->rank(array(array('kind' => 'psychic', 'signals' => array('issue:a' => 1.0))), 20, true, false);
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
            'negative threshold' => array(array(-0.1, 0.6, 0.3, 0.156, 0.402, 0.5)),
            'threshold past 1' => array(array(1.1, 0.6, 0.3, 0.156, 0.402, 0.5)),
            'fuzzy weight not a number' => array(array(0.15, 'heavy', 0.3, 0.156, 0.402, 0.5)),
            'fuzzy threshold past 1' => array(array(0.15, 0.6, 1.5, 0.156, 0.402, 0.5)),
            'semantic near not finite' => array(array(0.15, 0.6, 0.3, NAN, 0.402, 0.5)),
            'semantic far past 2' => array(array(0.15, 0.6, 0.3, 0.156, 2.1, 0.5)),
            'far not past near' => array(array(0.15, 0.6, 0.3, 0.4, 0.4, 0.5)),
            'boundary not a number' => array(array(0.15, 0.6, 0.3, 0.156, 0.402, 'half')),
            'boundary at the threshold' => array(array(0.5, 0.6, 0.3, 0.156, 0.402, 0.5)),
            'boundary under the threshold' => array(array(0.6, 0.6, 0.3, 0.156, 0.402, 0.5)),
            'boundary at 1' => array(array(0.15, 0.6, 0.3, 0.156, 0.402, 1)),
        );
    }

    public function testAllWordsMatchesGoFirstInTheirOwnBand()
    {
        $found = array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.5)),
            array('kind' => 'fuzzy', 'signals' => array('issue:b' => 1.0)),
        );

        $plain = $this->ranking()->rank($found, 20, true, false);
        $this->assertSame(array('issue:b', 'issue:a'), array_column($plain, 'key'));

        $banded = $this->ranking()->rank($found, 20, true, true);
        $this->assertSame(array('issue:a', 'issue:b'), array_column($banded, 'key'));
        $this->assertEqualsWithDelta($this->upper(0.5), $banded[0]['score'], 1e-12);
        $this->assertEqualsWithDelta($this->lower(0.6), $banded[1]['score'], 1e-12);
    }

    public function testBandsKeepTheOrderInsideAndNeverDropOrAddResults()
    {
        $found = array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.9, 'issue:b' => 0.2, 'issue:c' => 0.1)),
            array('kind' => 'fuzzy', 'signals' => array('issue:d' => 1.0, 'issue:e' => 0.5)),
        );

        $banded = $this->ranking()->rank($found, 20, true, true);

        $this->assertSame(array('issue:a', 'issue:b', 'issue:d', 'issue:e'), array_column($banded, 'key'));
        $this->assertEqualsWithDelta($this->upper(0.9), $banded[0]['score'], 1e-12);
        $this->assertEqualsWithDelta($this->upper(0.2), $banded[1]['score'], 1e-12);
        $this->assertEqualsWithDelta($this->lower(0.6), $banded[2]['score'], 1e-12);
        $this->assertEqualsWithDelta($this->lower(0.6 * (0.5 - 0.3) / 0.7), $banded[3]['score'], 1e-12);
    }

    public function testAKeywordMatchAlsoFoundByFuzzyGoesInTheAllWordsBand()
    {
        $banded = $this->ranking()->rank(array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.2)),
            array('kind' => 'fuzzy', 'signals' => array('issue:a' => 1.0, 'issue:b' => 1.0)),
        ), 20, true, true);

        $this->assertSame(array('issue:a', 'issue:b'), array_column($banded, 'key'));
        $this->assertEqualsWithDelta($this->upper(0.6), $banded[0]['score'], 1e-12);
    }

    public function testTheBandsFollowTheBoundaryAndAThresholdPastHalf()
    {
        $ranking = new StrongestSignal(0.6, 0.6, 0.3, 0.156, 0.402, 0.8);
        $banded = $ranking->rank(array(
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.7)),
            array('kind' => 'semantic', 'signals' => array('issue:b' => 0.156, 'issue:c' => 0.2)),
        ), 20, true, true);

        $this->assertSame(array('issue:a', 'issue:b', 'issue:c'), array_column($banded, 'key'));
        $this->assertEqualsWithDelta(0.8 + 0.2 * 0.6 + (0.1 / 0.4) * (1 - 0.92), $banded[0]['score'], 1e-12);
        $this->assertEqualsWithDelta(0.8, $banded[1]['score'], 1e-12);
        $this->assertGreaterThan(0.6, $banded[2]['score']);
        $this->assertLessThan($banded[1]['score'], $banded[2]['score']);
    }

    public function testExactMatchesKeepOneWhenBanded()
    {
        $banded = $this->ranking()->rank(array(
            array('kind' => 'exact', 'signals' => array('issue:x' => 1.0)),
            array('kind' => 'fts', 'signals' => array('issue:a' => 0.5)),
        ), 20, true, true);

        $this->assertSame(array('issue:x', 'issue:a'), array_column($banded, 'key'));
        $this->assertSame(1.0, $banded[0]['score']);
    }

    /**
     * @param float $threshold
     * @return StrongestSignal
     */
    private function ranking($threshold = 0.15)
    {
        return new StrongestSignal(
            $threshold,
            0.6,
            0.3,
            0.156,
            0.402,
            self::BOUNDARY
        );
    }

    /**
     * The banded score of an all-words match scoring $score, at the default threshold.
     *
     * @param float $score
     * @return float
     */
    private function upper($score)
    {
        $low = self::BOUNDARY + (1 - self::BOUNDARY) * 0.15;

        return $low + ($score - 0.15) / 0.85 * (1 - $low);
    }

    /**
     * The banded score of another match scoring $score, at the default threshold.
     *
     * @param float $score
     * @return float
     */
    private function lower($score)
    {
        return 0.15 + ($score - 0.15) / 0.85 * (self::BOUNDARY - 0.15);
    }
}
