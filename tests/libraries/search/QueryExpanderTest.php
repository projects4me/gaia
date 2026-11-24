<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Search;

use Gaia\Libraries\Search\QueryExpander;
use Gaia\Libraries\Search\SearchQuery;
use PHPUnit\Framework\TestCase;

/**
 * QueryExpander (agaia-4892): the other forms of a query's words it proposes, before any is checked against the
 * records.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class QueryExpanderTest extends TestCase
{
    public function testSplitsAJoinedWordTheMostEvenSplitFirst()
    {
        $this->assertSame(
            array(array('task', 'board'), array('taskb', 'oard'), array('tas', 'kboard'), array('taskbo', 'ard')),
            $this->forms('taskboard', array(0))
        );
    }

    public function testNeverSplitsAroundAStopWord()
    {
        $this->assertSame(array(), $this->proposals('logout'));
        $this->assertNotContains(array('check', 'out'), $this->forms('checkout', array(0)));
    }

    public function testJoinsTwoAdjacentWords()
    {
        $this->assertContains(array('at' => array(0, 1), 'form' => array('taskboard')), $this->proposals('task board'));
    }

    public function testNeverJoinsAcrossAStopWordOrANumber()
    {
        $this->assertSame(array(), $this->joins('log in'));
        $this->assertSame(array(), $this->joins('issue 4174'));
        $this->assertSame(array(), $this->joins('sign the form'));
    }

    /**
     * @dataProvider spellings
     */
    public function testProposesTheOtherSpelling($word, $other)
    {
        $this->assertContains(array($other), $this->forms($word, array(0)));
        $this->assertContains(array($word), $this->forms($other, array(0)));
    }

    /**
     * @return array
     */
    public static function spellings()
    {
        return array(
            'our' => array('colour', 'color'),
            'our plural' => array('colours', 'colors'),
            'ise' => array('organise', 'organize'),
            'isation' => array('organisation', 'organization'),
            'yse' => array('analyse', 'analyze'),
            'elled' => array('cancelled', 'canceled'),
            'tre' => array('centre', 'center'),
            'ogue' => array('catalogue', 'catalog'),
            'ence' => array('licence', 'license'),
        );
    }

    public function testLeavesShortWordsSpellingAlone()
    {
        $this->assertNotContains(array('tour'), $this->forms('tor', array(0)));
        $this->assertSame(array(), $this->forms('door', array(0)));
    }

    public function testProposesAnAbbreviationAndItsFullWordsBothWays()
    {
        $this->assertSame(array('configuration'), $this->forms('config', array(0))[0]);
        $this->assertContains(array('config'), $this->forms('configuration', array(0)));
        $this->assertSame(array(array('authentication'), array('authorization')), $this->forms('auth', array(0)));
        $this->assertSame(array('auth'), $this->forms('authorization', array(0))[0]);
    }

    public function testExpandsOnlyWordsOfLetters()
    {
        $this->assertSame(array(), $this->proposals('4170'));
        $this->assertSame(array(), $this->proposals('the and'));
        $this->assertSame(array(), $this->proposals('colour2000'));
    }

    public function testExpandsOnlyWordsThatStandAloneInTheText()
    {
        $this->assertSame(array(), $this->proposals('config.php'));
        $this->assertSame(array(), $this->proposals('colour-blind'));
        $this->assertSame(array(array('color')), $this->forms('"colour"', array(0)));
        $this->assertSame(array(), $this->joins('task board.php'));
    }

    public function testARepeatedWordGetsItsFormsAtEachPositionCountedOnce()
    {
        $proposals = (new QueryExpander(2, 5, 3))->proposals(new SearchQuery('colour colour config'));

        $this->assertSame(array(
            array('at' => array(0), 'form' => array('color')),
            array('at' => array(1), 'form' => array('color')),
            array('at' => array(2), 'form' => array('configuration')),
        ), $proposals);
    }

    public function testPutsWordFormsBeforeJoinsAndJoinsBeforeSplits()
    {
        $proposals = (new QueryExpander(16, 5, 3))->proposals(new SearchQuery('taskboard config'));

        $this->assertSame(array('at' => array(1), 'form' => array('configuration')), $proposals[0]);
        $this->assertSame(array('at' => array(0, 1), 'form' => array('taskboardconfig')), $proposals[1]);
        $this->assertSame(array('at' => array(0), 'form' => array('task', 'board')), $proposals[2]);
    }

    public function testProposesAtMostTheMaximum()
    {
        $long = 'abcdefghijklmnopqrstuvwxyz';

        $this->assertCount(16, (new QueryExpander(16, 5, 3))->proposals(new SearchQuery($long)));
        $this->assertCount(3, (new QueryExpander(3, 5, 3))->proposals(new SearchQuery($long)));
        $this->assertSame(
            array('at' => array(0), 'form' => array('abcdefghijklm', 'nopqrstuvwxyz')),
            (new QueryExpander(1, 5, 3))->proposals(new SearchQuery($long))[0]
        );
    }

    /**
     * @dataProvider badMaximums
     */
    public function testRefusesASettingThatIsNotAWholeNumberFromOne($max)
    {
        foreach (array(array($max, 5, 3), array(16, $max, 3), array(16, 5, $max)) as $arguments) {
            try {
                new QueryExpander(...$arguments);
                $this->fail('Accepted ' . var_export($max, true));
            } catch (\Gaia\Exception\Exception $e) {
                $this->assertStringContainsString('whole number from 1', $e->getMessage());
            }
        }
    }

    public function testTheLengthSettingsDecideWhatIsRespelledAndSplit()
    {
        $this->assertSame(array(), (new QueryExpander(16, 7, 3))->proposals(new SearchQuery('colour')));
        $this->assertSame(
            array(
                array('at' => array(0), 'form' => array('task', 'board')),
                array('at' => array(0), 'form' => array('taskb', 'oard')),
            ),
            (new QueryExpander(16, 5, 4))->proposals(new SearchQuery('taskboard'))
        );
    }

    /**
     * @return array
     */
    public static function badMaximums()
    {
        return array('zero' => array(0), 'negative' => array(-1), 'float' => array(1.5), 'string' => array('16'));
    }

    /**
     * @param string $query
     * @return array
     */
    private function proposals($query)
    {
        return (new QueryExpander(16, 5, 3))->proposals(new SearchQuery($query));
    }

    /**
     * The forms proposed for the given positions.
     *
     * @param string $query
     * @param array $at
     * @return array
     */
    private function forms($query, array $at)
    {
        $forms = array();
        foreach ($this->proposals($query) as $proposal) {
            if ($proposal['at'] === $at) {
                $forms[] = $proposal['form'];
            }
        }

        return $forms;
    }

    /**
     * @param string $query
     * @return array
     */
    private function joins($query)
    {
        return array_values(array_filter($this->proposals($query), function ($proposal) {
            return count($proposal['at']) === 2;
        }));
    }
}
