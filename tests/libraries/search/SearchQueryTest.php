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

    public function testReadsPhrasesAndExcludedWords()
    {
        $query = new SearchQuery('"ledger export" fails -csv -Bank-sync not-excluded');

        $this->assertSame(array('ledger export'), $query->phrases());
        $this->assertSame(array('csv', 'bank', 'sync'), $query->excluded());
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
}
