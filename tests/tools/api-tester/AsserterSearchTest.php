<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\ApiTester;

use ApiTester\Asserter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Asserter's ordered-list and score assertions used by the search catalog (agaia-4886):
 * idsEqual, idsWithin, idsBefore, idsSameAs, scoresWithin and scoresFalling.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AsserterSearchTest extends TestCase
{
    /**
     * @var Asserter
     */
    private $asserter;

    protected function setUp(): void
    {
        $this->asserter = new Asserter();
    }

    public function testIdsEqualPassesOnTheSameIdsInTheSameOrder()
    {
        $this->assertSame([], $this->check(['idsEqual' => ['a', 'b']], ['a', 'b']));
    }

    public function testIdsEqualFailsOnAnotherOrderOrAnExtraId()
    {
        $this->assertCount(1, $this->check(['idsEqual' => ['a', 'b']], ['b', 'a']));
        $this->assertCount(1, $this->check(['idsEqual' => ['a']], ['a', 'b']));
    }

    public function testAnEmptyIdsEqualAsksForNoResults()
    {
        $this->assertSame([], $this->check(['idsEqual' => []], []));
        $this->assertSame(['idsEqual: expected [], got [a]'], $this->check(['idsEqual' => []], ['a']));
    }

    public function testIdsWithinChecksEveryIdIsInTheTopRank()
    {
        $this->assertSame([], $this->check(['idsWithin' => ['rank' => 2, 'ids' => ['b', 'a']]], ['a', 'b', 'c']));
        $this->assertSame(
            ['idsWithin: c is not in the top 1'],
            $this->check(['idsWithin' => ['rank' => 1, 'ids' => ['c']]], ['a', 'b', 'c'])
        );
    }

    public function testIdsBeforeFailsWhenALaterIdRanksAbove()
    {
        $expects = ['idsBefore' => ['ids' => ['a'], 'before' => ['b', 'z']]];

        $this->assertSame([], $this->check($expects, ['a', 'c', 'b']));
        $this->assertSame(['idsBefore: b ranks above a'], $this->check($expects, ['b', 'a']));
        $this->assertSame(['idsBefore: missing id a'], $this->check($expects, ['b']));
    }

    public function testIdsSameAsComparesAsSetsUnlessOrdered()
    {
        $this->remember('x', ['b', 'a']);

        $this->assertSame([], $this->check(['idsSameAs' => ['case' => 'x']], ['a', 'b']));
        $this->assertCount(1, $this->check(['idsSameAs' => ['case' => 'x', 'ordered' => true]], ['a', 'b']));
    }

    public function testIdsSameAsComparesOnlyTheTopWhenGiven()
    {
        $this->remember('x', ['a', 'b', 'c']);
        $expects = ['idsSameAs' => ['case' => 'x', 'top' => 2, 'ordered' => true]];

        $this->assertSame([], $this->check($expects, ['a', 'b']));
        $this->assertCount(1, $this->check($expects, ['a', 'c']));
    }

    public function testIdsSameAsFailsWhenTheOtherCaseHasNoIds()
    {
        $this->assertSame(
            ['idsSameAs: case x has no results to compare with (not run, skipped, failed or empty)'],
            $this->check(['idsSameAs' => ['case' => 'x']], ['a'])
        );
        $this->remember('y', []);
        $this->assertCount(1, $this->check(['idsSameAs' => ['case' => 'y']], []));
    }

    public function testIdsWithinFailsWithoutARankOrIds()
    {
        $message = ['idsWithin: needs a rank of 1 or more and at least one id'];

        $this->assertSame($message, $this->check(['idsWithin' => ['rank' => 3]], ['a']));
        $this->assertSame(
            array_merge($message, ['idsWithin: a is not in the top 0']),
            $this->check(['idsWithin' => ['ids' => ['a']]], ['a'])
        );
    }

    public function testScoresWithinFailsOnAScoreOutsideTheRangeOrMissing()
    {
        $this->assertSame([], $this->checkScores(['scoresWithin' => [0.15, 1]], ['a' => 1, 'b' => 0.15]));
        $this->assertSame(
            ['scoresWithin: b scores 0.1, outside [0.15, 1]'],
            $this->checkScores(['scoresWithin' => [0.15, 1]], ['a' => 1, 'b' => 0.1])
        );
        $this->assertCount(1, $this->checkScores(['scoresWithin' => [0, 1]], ['a' => null]));
    }

    public function testScoresFallingFailsWhenAScoreRises()
    {
        $this->assertSame([], $this->checkScores(['scoresFalling' => true], ['a' => 1, 'b' => 0.5, 'c' => 0.5]));
        $this->assertSame(
            ['scoresFalling: c scores 0.7, above the result before it (0.5)'],
            $this->checkScores(['scoresFalling' => true], ['a' => 1, 'b' => 0.5, 'c' => 0.7])
        );
    }

    public function testScoresFallingFailsOnAMissingScore()
    {
        $this->assertSame(
            ['scoresFalling: b has no numeric score'],
            $this->checkScores(['scoresFalling' => true], ['a' => 1, 'b' => null])
        );
    }

    /**
     * @param string $caseId
     * @param string[] $ids
     */
    private function remember($caseId, array $ids)
    {
        $data = array_map(function ($id) {
            return ['type' => 'searchresult', 'id' => $id];
        }, $ids);
        $this->asserter->rememberCase($caseId, ['status' => 200, 'json' => ['data' => $data]]);
    }

    /**
     * @param array $expects
     * @param string[] $ids The ids of the response's data items, in order
     * @return string[] failures
     */
    private function check(array $expects, array $ids)
    {
        return $this->checkScores($expects, array_fill_keys($ids, 0.5));
    }

    /**
     * @param array $expects
     * @param array $scores id => attributes.score, in order
     * @return string[] failures
     */
    private function checkScores(array $expects, array $scores)
    {
        $data = [];
        foreach ($scores as $id => $score) {
            $data[] = ['type' => 'searchresult', 'id' => (string) $id, 'attributes' => ['score' => $score]];
        }

        return $this->asserter->assert($expects, ['status' => 200, 'json' => ['data' => $data]]);
    }
}
