<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Db\Migration\Postgres;
use Gaia\Libraries\Search\SearchableRegistry;
use Gaia\Libraries\Search\SearchVocabulary;
use Gaia\Libraries\Search\SearchVocabularyBuilder;
use Gaia\Libraries\Search\VocabularySource;
use PHPUnit\Framework\TestCase;

/**
 * Rebuilds the vocabulary of a fixture table on the test database and reads it back (agaia-4891, D-010). The
 * fixture tables are dropped afterwards.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchVocabularyTest extends TestCase
{
    private const TYPE = 'vocabulary_fixture';
    private const RECORDS = 'test_vocabulary_records';

    /**
     * @var \Phalcon\Db\Adapter\Pdo\Postgresql
     */
    private $db;

    /**
     * @var VocabularySource
     */
    private $source;

    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
        $db = \Phalcon\Di\Di::getDefault()->getShared('db');
        if ($db->getType() !== 'pgsql') {
            $this->markTestSkipped('The search vocabulary needs a PostgreSQL database');
        }
        $this->db = $db;
        $this->dropFixture();
        $this->db->execute(
            'CREATE TABLE "' . self::RECORDS . '" ("id" varchar(36) PRIMARY KEY, "projectId" varchar(36),'
            . ' "subject" varchar(255), "description" text, "deleted" smallint NOT NULL DEFAULT 0)'
        );
        $this->source = new VocabularySource(self::TYPE, self::RECORDS, array('subject', 'description'), 'projectId');
        (new Postgres())->syncSearchVocabulary($this->db, $this->source->table(), $this->source->signature());

        $this->record('r1', 'p1', 'Login fails', '<p>The login page <b>fails</b> on Safari.</p>');
        $this->record('r2', 'p1', 'Login page slow', 'Page&nbsp;loads slowly; login-page cache');
        $this->record('r3', 'p2', 'Fails', 'login ' . str_repeat('x', SearchVocabularyBuilder::MAX_WORD_LENGTH + 1));
        $this->record('r4', 'p1', 'Login secret', null, 1);
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->dropFixture();
        }
    }

    public function testARebuildCountsTheRecordsEachWordOccursIn()
    {
        $counts = $this->builder()->rebuild($this->source);
        $vocabulary = new SearchVocabulary(self::TYPE, null, $this->db);

        $this->assertSame(3, $vocabulary->frequency('login'));
        $this->assertSame(2, $vocabulary->frequency('page'), 'a word repeated in a record counts once');
        $this->assertSame(3, $vocabulary->frequency(' LOGIN '), 'words are compared in lower case');
        $this->assertSame(1, $vocabulary->frequency('safari'), 'punctuation is read as a space');
        $this->assertSame(0, $vocabulary->frequency('secret'), 'deleted records are left out');
        $this->assertSame(0, $vocabulary->frequency('p'), 'tags are not words');
        $this->assertSame(0, $vocabulary->frequency('nbsp'), 'entities are not words');
        $this->assertSame(0, $vocabulary->frequency(str_repeat('x', SearchVocabularyBuilder::MAX_WORD_LENGTH + 1)));
        $this->assertTrue($vocabulary->has('cache'));
        $this->assertFalse($vocabulary->has('missing'));
        $this->assertFalse($vocabulary->has(''));
        $this->assertSame(array('words' => 10, 'pairs' => 11), $counts);
    }

    public function testARebuildCountsAdjacentPairsWithinOneField()
    {
        $this->builder()->rebuild($this->source);
        $vocabulary = new SearchVocabulary(self::TYPE, null, $this->db);

        $this->assertSame(2, $vocabulary->pairFrequency('login', 'page'));
        $this->assertSame(1, $vocabulary->pairFrequency('Login', 'Fails'));
        $this->assertSame(1, $vocabulary->pairFrequency('page', 'loads'), 'an entity separates words');
        $this->assertSame(0, $vocabulary->pairFrequency('page', 'login'), 'a pair is ordered');
        $this->assertSame(0, $vocabulary->pairFrequency('fails', 'the'), 'a pair never spans two fields');
        $this->assertSame(0, $vocabulary->pairFrequency('login', ''), 'a word alone is not a pair');
        $this->assertSame(
            0,
            $vocabulary->pairFrequency('login', str_repeat('x', SearchVocabularyBuilder::MAX_WORD_LENGTH + 1))
        );
    }

    public function testTheCountsAreSummedOverTheGivenProjects()
    {
        $this->builder()->rebuild($this->source);

        $this->assertSame(2, (new SearchVocabulary(self::TYPE, array('p1'), $this->db))->frequency('login'));
        $this->assertSame(1, (new SearchVocabulary(self::TYPE, array('p2'), $this->db))->frequency('login'));
        $this->assertSame(3, (new SearchVocabulary(self::TYPE, array('p1', 'p2', 'p3'), $this->db))->frequency('login'));
        $this->assertSame(0, (new SearchVocabulary(self::TYPE, array('p2'), $this->db))->frequency('safari'));
        $this->assertSame(0, (new SearchVocabulary(self::TYPE, array(), $this->db))->frequency('login'));
    }

    public function testANewRecordsWordsAppearAfterTheNextRebuild()
    {
        $this->builder()->rebuild($this->source);
        $this->record('r5', 'p2', 'Zebra crossing', 'Café');
        $vocabulary = new SearchVocabulary(self::TYPE, null, $this->db);

        $this->assertSame(0, $vocabulary->frequency('zebra'));
        $this->builder()->rebuild($this->source);
        $this->assertSame(1, $vocabulary->frequency('zebra'));
        $this->assertSame(1, $vocabulary->pairFrequency('zebra', 'crossing'));
        $this->assertSame(1, $vocabulary->frequency('CAFÉ'), 'letters outside ASCII are letters');
        $this->assertSame(3, $vocabulary->frequency('login'), 'a rebuild replaces the counts, it does not add to them');
    }

    public function testAWordIsReadAsTheBuilderStoresIt()
    {
        $this->builder()->rebuild($this->source);
        $vocabulary = new SearchVocabulary(self::TYPE, null, $this->db);

        $this->assertSame(1, $vocabulary->frequency('Safari.'), 'punctuation around a word is dropped');
        $this->assertSame(0, $vocabulary->frequency('login-page'), 'more than one word is never stored');
        $this->assertSame(0, $vocabulary->pairFrequency('login page', 'cache'));
    }

    public function testALoneLessThanSignIsNotATag()
    {
        $this->record('r5', 'p2', 'if a < b and c > d', null);
        $this->builder()->rebuild($this->source);

        $this->assertSame(1, (new SearchVocabulary(self::TYPE, null, $this->db))->pairFrequency('b', 'and'));
    }

    public function testARebuildDropsTheWordsNoRecordHoldsAnyMore()
    {
        $this->builder()->rebuild($this->source);
        $this->db->execute('UPDATE "' . self::RECORDS . '" SET "description" = NULL WHERE "id" = ?', array('r1'));
        $this->builder()->rebuild($this->source);
        $vocabulary = new SearchVocabulary(self::TYPE, null, $this->db);

        $this->assertSame(0, $vocabulary->frequency('safari'));
        $this->assertSame(1, $vocabulary->pairFrequency('login', 'fails'), 'the subject is still counted');
    }

    public function testAnUnknownTypeIsRefused()
    {
        $registry = $this->createMock(SearchableRegistry::class);
        $registry->method('forType')->willReturn(null);
        $builder = new SearchVocabularyBuilder($registry, $this->createMock(\Gaia\Libraries\Meta\Manager::class), $this->db);

        $this->expectException(\Gaia\Exception\Exception::class);

        $builder->run('nothing');
    }

    private function builder()
    {
        return new SearchVocabularyBuilder(
            $this->createMock(SearchableRegistry::class),
            $this->createMock(\Gaia\Libraries\Meta\Manager::class),
            $this->db
        );
    }

    private function record($id, $projectId, $subject, $description, $deleted = 0)
    {
        $this->db->execute(
            'INSERT INTO "' . self::RECORDS . '" ("id", "projectId", "subject", "description", "deleted")'
            . ' VALUES (?, ?, ?, ?, ?)',
            array($id, $projectId, $subject, $description, $deleted)
        );
    }

    private function dropFixture()
    {
        $this->db->execute('DROP TABLE IF EXISTS "' . VocabularySource::tableFor(self::TYPE) . '"');
        $this->db->execute('DROP TABLE IF EXISTS "' . self::RECORDS . '"');
    }
}
