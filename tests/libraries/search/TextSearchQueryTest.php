<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Search;

use Gaia\Libraries\Search\KeywordQuery;
use Gaia\Libraries\Search\SearchQuery;
use Gaia\Libraries\Search\SearchTarget;
use Gaia\Libraries\Search\TextSearchQuery;
use PHPUnit\Framework\TestCase;

/**
 * TextSearchQuery and KeywordQuery (agaia-4889, agaia-4892): the tsquery SQL of a keyword query, every value bound,
 * the kept alternatives ORed into the plain words.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TextSearchQueryTest extends TestCase
{
    /**
     * @var string
     */
    private const CONFIG = 'CAST(:search_config AS regconfig)';

    public function testAQueryWithoutAlternativesIsItsPlainWords()
    {
        $tsquery = (new TextSearchQuery())->expression($this->target(), KeywordQuery::plain('"ledger" -export'));

        $this->assertSame('(plainto_tsquery(' . self::CONFIG . ', :search_fts))', $tsquery['sql']);
        $this->assertSame(array('search_config' => 'simple', 'search_fts' => '"ledger" -export'), $tsquery['bind']);
    }

    public function testThePartsOfAPathOrFileNameAreReadAsWords()
    {
        $tsquery = (new TextSearchQuery())->expression(
            $this->target(),
            KeywordQuery::plain('src/OAuth/callback image-upload.js meta.semantic file_name C:\\dir')
        );

        $this->assertSame(
            'src OAuth callback image-upload js meta semantic file name C: dir',
            $tsquery['bind']['search_fts']
        );
    }

    public function testTheWordsWithAlternativesAreReadApartToo()
    {
        $keywords = KeywordQuery::expanded(new SearchQuery('docs/colour'), array(
            array('at' => array(0), 'form' => array('color')),
        ));

        $tsquery = (new TextSearchQuery())->expression($this->target(), $keywords);

        $this->assertNotContains('docs/colour', $tsquery['bind']);
        foreach ($tsquery['bind'] as $value) {
            $this->assertStringNotContainsString('/', $value);
        }
    }

    public function testAQueryWithNoKeptAlternativeKeepsItsTextAsItIs()
    {
        $keywords = KeywordQuery::expanded(new SearchQuery('real-time "board"'), array());

        $this->assertSame('real-time "board"', $keywords->text());
        $this->assertSame(array(), $keywords->groups());
    }

    public function testOrsAWordsFormsAndAJoinIntoThePlainWords()
    {
        $keywords = KeywordQuery::expanded(new SearchQuery('Fix colour task board'), array(
            array('at' => array(1), 'form' => array('color')),
            array('at' => array(2, 3), 'form' => array('taskboard')),
        ));

        $tsquery = (new TextSearchQuery())->expression($this->target(), $keywords);

        $this->assertSame(
            '(' . $this->words('search_fts')
            . ' && ((' . $this->words('search_fts1') . ' && ' . $this->words('search_fts2') . ') || '
            . $this->words('search_fts3') . ')'
            . ' && (' . $this->words('search_fts4') . ' || ' . $this->words('search_fts5') . '))',
            $tsquery['sql']
        );
        $this->assertSame(array(
            'search_config' => 'simple',
            'search_fts' => 'Fix',
            'search_fts1' => 'task',
            'search_fts2' => 'board',
            'search_fts3' => 'taskboard',
            'search_fts4' => 'colour',
            'search_fts5' => 'color',
        ), $tsquery['bind']);
    }

    public function testKeepsTheTextAsTypedAroundTheWordsWithAlternatives()
    {
        $keywords = KeywordQuery::expanded(new SearchQuery('colour  config.php "v1.2"'), array(
            array('at' => array(0), 'form' => array('color')),
        ));

        $this->assertSame('config.php "v1.2"', $keywords->text());
    }

    public function testASplitIsAPhraseAndAWordWithoutPlainWordsLeftHasNoEmptyPart()
    {
        $keywords = KeywordQuery::expanded(new SearchQuery('taskboard'), array(
            array('at' => array(0), 'form' => array('task', 'board')),
        ));

        $tsquery = (new TextSearchQuery())->expression($this->target(), $keywords);

        $this->assertSame(
            '((' . $this->words('search_fts1') . ' || phraseto_tsquery(' . self::CONFIG . ', :search_fts2)))',
            $tsquery['sql']
        );
        $this->assertSame(
            array('search_config' => 'simple', 'search_fts1' => 'taskboard', 'search_fts2' => 'task board'),
            $tsquery['bind']
        );
    }

    public function testAJoinTakesTheFormsOfItsWordsAndAnOverlappingJoinIsLeftOut()
    {
        $keywords = KeywordQuery::expanded(new SearchQuery('task board config'), array(
            array('at' => array(2), 'form' => array('configuration')),
            array('at' => array(0, 1), 'form' => array('taskboard')),
            array('at' => array(1, 2), 'form' => array('boardconfig')),
        ));

        $this->assertSame('', $keywords->text());
        $this->assertSame(array(
            array('words' => array('task', 'board'), 'forms' => array(array(), array()), 'joined' => 'taskboard'),
            array('words' => array('config'), 'forms' => array(array(array('configuration'))), 'joined' => null),
        ), $keywords->groups());
    }

    public function testAPhraseIsBound()
    {
        $tsquery = (new TextSearchQuery())->phrase($this->target(), 'task board');

        $this->assertSame('(phraseto_tsquery(' . self::CONFIG . ', :search_phrase))', $tsquery['sql']);
        $this->assertSame(array('search_config' => 'simple', 'search_phrase' => 'task board'), $tsquery['bind']);
    }

    /**
     * @param string $parameter
     * @return string
     */
    private function words($parameter)
    {
        return 'plainto_tsquery(' . self::CONFIG . ', :' . $parameter . ')';
    }

    /**
     * @return SearchTarget
     */
    private function target()
    {
        return new SearchTarget(
            array('type' => 'issue', 'model' => 'Issue'),
            'issues',
            array('searchVector' => array('generated' => array('config' => 'simple'))),
            'SELECT 1',
            array()
        );
    }
}
