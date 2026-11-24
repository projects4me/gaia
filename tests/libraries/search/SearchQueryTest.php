<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Search;

use Gaia\Libraries\Search\SearchQuery;
use PHPUnit\Framework\TestCase;

/**
 * Parsing a search query once for every searcher (agaia-4888).
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchQueryTest extends TestCase
{
    public function testSplitsTheWordsAndLowercasesThem()
    {
        $query = new SearchQuery('The Ledger-export of #123 fails');

        $this->assertSame('The Ledger-export of #123 fails', $query->text());
        $this->assertSame(array('the', 'ledger', 'export', 'of', '123', 'fails'), $query->words());
        $this->assertSame(array('ledger', 'export', '123', 'fails'), $query->nonStopWords());
        $this->assertSame(array('123'), $query->numbers());
        $this->assertTrue($query->hasWords());
    }

    public function testKeepsLettersOfOtherScripts()
    {
        $query = new SearchQuery('проблема Входа');

        $this->assertSame(array('проблема', 'входа'), $query->words());
        $this->assertSame(13, $query->nonStopLength());
    }

    public function testQuotesAndADashAreReadAsPlainWords()
    {
        $query = new SearchQuery('"ledger export" fails -csv');

        $this->assertSame(array('ledger', 'export', 'fails', 'csv'), $query->words());
    }

    public function testAQueryOfSymbolsHasNoWords()
    {
        $query = new SearchQuery('!!! ???');

        $this->assertSame(array(), $query->words());
        $this->assertFalse($query->hasWords());
        $this->assertSame(0, $query->nonStopLength());
    }

    public function testAQueryOfStopWordsHasNoOtherWords()
    {
        $query = new SearchQuery('the and of');

        $this->assertTrue($query->hasWords());
        $this->assertSame(array(), $query->nonStopWords());
        $this->assertSame(0, $query->nonStopLength());
    }

    public function testSeveralWordsMeansTwoNonStopWords()
    {
        $this->assertTrue((new SearchQuery('ledger export'))->hasSeveralWords());
        $this->assertFalse((new SearchQuery('the ledger'))->hasSeveralWords());
        $this->assertFalse((new SearchQuery('ledger Ledger ledger'))->hasSeveralWords());
    }
    /**
     * @dataProvider numberQueries
     */
    public function testANumberAloneIsANumberQuery($text, $number)
    {
        $this->assertSame($number, (new SearchQuery($text))->number());
    }

    /**
     * @return array
     */
    public static function numberQueries()
    {
        return array(
            'digits' => array('4170', '4170'),
            'with a hash' => array('#4170', '4170'),
            'leading zeros kept' => array('0917', '0917'),
            'inside text' => array('issue 4170', null),
            'a hash alone' => array('#', null),
            'two hashes' => array('##4170', null),
            'a reference' => array('agaia-4170', null),
            'a decimal' => array('41.70', null),
            'too long for any integer' => array('12345678901234567890', '12345678901234567890'),
        );
    }

    /**
     * @dataProvider referenceQueries
     */
    public function testAPrefixDashNumberIsAReference($text, $reference)
    {
        $this->assertSame($reference, (new SearchQuery($text))->reference());
    }

    /**
     * @return array
     */
    public static function referenceQueries()
    {
        return array(
            'short code' => array('agaia-4170', array('prefix' => 'agaia', 'number' => '4170')),
            'any case, as typed' => array('AGaia-4170', array('prefix' => 'AGaia', 'number' => '4170')),
            'letters and digits' => array('p4m-12', array('prefix' => 'p4m', 'number' => '12')),
            'a number alone' => array('4170', null),
            'starting with a digit' => array('4agaia-4170', null),
            'inside text' => array('see agaia-4170', null),
            'two dashes' => array('agaia--4170', null),
            'no number' => array('agaia-', null),
            'letters after the number' => array('covid-19x', null),
            'digits of another script' => array("ab-\u{0664}\u{0661}", null),
        );
    }

    public function testFindsTheNumbersWrittenWithAHash()
    {
        $query = new SearchQuery('Pasted (see #3969), and #12 or a#5, #7b, 44 and ##8');

        $this->assertSame(array('3969', '12'), $query->hashNumbers());
    }
}
