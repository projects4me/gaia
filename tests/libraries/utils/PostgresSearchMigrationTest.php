<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Db\Migration\Postgres;
use Gaia\Db\Migration\PostgresSearchDdl;
use Gaia\Libraries\Meta\Manager;
use Gaia\Libraries\Meta\Migration;
use PHPUnit\Framework\TestCase;

/**
 * Migrates a fixture model with a vector field, a generated tsvector field and
 * gin / gin_trgm / hnsw_cosine indexes against the test database, then
 * re-migrates it (agaia-4654). The fixture table is dropped afterwards.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PostgresSearchMigrationTest extends TestCase
{
    private const TABLE = 'test_search_fixtures';

    /**
     * @var \Phalcon\Db\Adapter\Pdo\Postgresql
     */
    private $db;

    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class) || !class_exists(Migration::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
        $db = \Phalcon\Di\Di::getDefault()->getShared('db');
        if ($db->getType() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL search columns need a PostgreSQL database');
        }
        $this->db = $db;
        $this->dropFixture();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->dropFixture();
        }
    }

    public function testExtensionsAreCreatedIdempotentlyAndMissingOnesSkipped()
    {
        $handler = new Postgres();

        $this->assertSame(array(), $handler->createExtensions($this->db, array('pg_trgm')));
        $this->assertSame(array(), $handler->createExtensions($this->db, array('pg_trgm')));
        $this->assertSame(
            array('gaia_no_such_extension'),
            $handler->createExtensions($this->db, array('gaia_no_such_extension'))
        );
        $this->assertTrue($this->extensionInstalled('pg_trgm'));
    }

    public function testConfiguredExtensionsIncludeUuidOssp()
    {
        $config = require __DIR__ . '/../../../config/database/Postgres.php';

        $this->assertContains('uuid-ossp', $config['extensions']);
        $this->assertSame(array(), (new Postgres())->createExtensions($this->db, array('uuid-ossp')));
        $this->assertTrue($this->extensionInstalled('uuid-ossp'));
    }

    public function testTsvectorAndTrigramIndexMigrateAndReMigrateCleanly()
    {
        (new Postgres())->createExtensions($this->db, array('pg_trgm'));
        $meta = $this->fixtureMeta(false);

        $this->migrate($meta);

        $this->assertSame('tsvector', $this->columnType('searchVector'));
        $this->assertSame('s', $this->column('searchVector')['attgenerated']);
        $this->assertStringContainsString('USING gin ("searchVector")', $this->indexDefinition('searchVector'));
        $this->assertStringContainsString('gin_trgm_ops', $this->indexDefinition('subject'));
        $this->assertSame('character varying(255)', $this->columnType('subject'));

        $this->db->execute(
            'INSERT INTO "' . self::TABLE . '" ("id", "subject", "description") VALUES (?, ?, ?)',
            array('fixture-1', 'Search planning', 'vectors and trigrams')
        );
        $row = $this->db->fetchOne(
            'SELECT "searchVector"::text AS v FROM "' . self::TABLE . '" WHERE "id" = ?',
            \PDO::FETCH_ASSOC,
            array('fixture-1')
        );
        $this->assertStringContainsString("'search':1A", $row['v']);
        $this->assertStringContainsString("'trigram':5B", $row['v']);

        // Re-migrating leaves the generated column itself in place.
        $before = $this->column('searchVector')['attnum'];
        $this->migrate($meta);
        $this->assertSame($before, $this->column('searchVector')['attnum']);
        $this->assertNotEmpty($this->indexDefinition('searchVector'));

        // Changing the sources recreates the column and its index.
        $meta['TestSearchFixture']['fields']['searchVector']['generated']['sources'] = array('subject' => 'B');
        $this->migrate($meta);
        $this->assertNotSame($before, $this->column('searchVector')['attnum']);
        $this->assertStringContainsString('USING gin ("searchVector")', $this->indexDefinition('searchVector'));
        $row = $this->db->fetchOne(
            'SELECT "searchVector"::text AS v FROM "' . self::TABLE . '" WHERE "id" = ?',
            \PDO::FETCH_ASSOC,
            array('fixture-1')
        );
        $this->assertSame("'plan':2B 'search':1B", $row['v']);
    }

    public function testAColumnBuiltBeforePathsWereSplitIsRebuiltWithTheirWords()
    {
        $meta = $this->fixtureMeta(false);
        $this->migrate($meta);
        // The column as it was before agaia-4894: each source as one tsvector, paths and file names kept whole.
        $old = "tsvector GENERATED ALWAYS AS ("
            . "setweight(to_tsvector('english'::regconfig, coalesce(\"subject\"::text, '')), 'A')"
            . " || setweight(to_tsvector('english'::regconfig, coalesce(\"description\"::text, '')), 'B')"
            . ") STORED";
        $this->db->execute('ALTER TABLE "' . self::TABLE . '" DROP COLUMN "searchVector"');
        $this->db->execute('ALTER TABLE "' . self::TABLE . '" ADD COLUMN "searchVector" ' . $old);
        $this->db->execute(
            'COMMENT ON COLUMN "' . self::TABLE . '"."searchVector" IS '
            . $this->db->escapeString((new PostgresSearchDdl())->signature($old))
        );
        $this->db->execute(
            'INSERT INTO "' . self::TABLE . '" ("id", "subject", "description") VALUES (?, ?, ?)',
            array('fixture-1', 'OAuth/callback loops', 'search in image-upload.js')
        );
        $before = $this->column('searchVector')['attnum'];

        $this->migrate($meta);

        $this->assertNotSame($before, $this->column('searchVector')['attnum']);
        $this->assertStringContainsString('USING gin ("searchVector")', $this->indexDefinition('searchVector'));
        $row = $this->db->fetchOne(
            'SELECT "searchVector"::text AS v FROM "' . self::TABLE . '" WHERE "id" = ?',
            \PDO::FETCH_ASSOC,
            array('fixture-1')
        );
        $this->assertStringContainsString("'oauth/callback':1A", $row['v']);
        foreach (array('image-upload.js', 'oauth', 'callback', 'imag', 'upload', 'js') as $word) {
            $this->assertMatchesRegularExpression("/'{$word}':\\d+[AB]/", $row['v']);
        }
        // A word the text has already is not added again.
        $this->assertMatchesRegularExpression("/'search':\\d+B /", $row['v']);
    }

    /**
     * Runs against a server with pgvector (image pgvector/pgvector:pg16). On a server
     * without it, only the skip report of createExtensions can be checked.
     */
    public function testVectorFieldWithHnswIndexMigratesAndReMigratesCleanly()
    {
        $skipped = (new Postgres())->createExtensions($this->db, array('vector', 'pg_trgm'));
        $meta = $this->fixtureMeta(true);
        if (!empty($skipped)) {
            // Without pgvector the table fails before any DDL, naming the extension.
            $this->assertSame(array('vector'), $skipped);
            try {
                $this->migrate($meta);
                $this->fail('A vector field migrated without the vector extension');
            } catch (\Gaia\Exception\MigrationDriver $e) {
                $this->assertStringContainsString('extension vector', $e->getMessage());
            }
            $this->assertFalse($this->tableExists(), 'The table was created before the extension check');
            return;
        }

        $this->migrate($meta);
        $before = $this->column('embedding')['attnum'];
        $this->migrate($meta);

        $this->assertSame('vector(3)', $this->columnType('embedding'));
        $this->assertSame($before, $this->column('embedding')['attnum']);
        $this->assertStringContainsString('USING hnsw (embedding vector_cosine_ops)', $this->indexDefinition('embedding'));

        // A dimension change never drops stored embeddings.
        $this->db->execute(
            'INSERT INTO "' . self::TABLE . '" ("id", "embedding") VALUES (?, ?)',
            array('fixture-1', '[1,2,3]')
        );
        $meta['TestSearchFixture']['fields']['embedding']['dimensions'] = 4;
        try {
            $this->migrate($meta);
            $this->fail('A filled vector column was recreated');
        } catch (\Gaia\Exception\MigrationDriver $e) {
            $this->assertSame('vector(3)', $this->columnType('embedding'));
        }
        $row = $this->db->fetchOne(
            'SELECT "embedding"::text AS v FROM "' . self::TABLE . '" WHERE "id" = ?',
            \PDO::FETCH_ASSOC,
            array('fixture-1')
        );
        $this->assertSame('[1,2,3]', $row['v']);

        // Once cleared, it is recreated with the new dimensions.
        $this->db->execute('UPDATE "' . self::TABLE . '" SET "embedding" = NULL');
        $this->migrate($meta);
        $this->assertSame('vector(4)', $this->columnType('embedding'));
    }

    public function testSearchIndexReplacesAnIndexOfAnotherKind()
    {
        (new Postgres())->createExtensions($this->db, array('pg_trgm'));
        $meta = $this->fixtureMeta(false);
        $meta['TestSearchFixture']['indexes']['subject'] = 'index';
        $this->migrate($meta);
        $this->assertStringContainsString('USING btree (subject)', $this->indexDefinition('subject'));

        $meta['TestSearchFixture']['indexes']['subject'] = 'gin_trgm';
        $this->migrate($meta);

        $this->assertStringContainsString('USING gin (subject gin_trgm_ops)', $this->indexDefinition('subject'));

        // And back: a unique index replaces the trigram index of the same name.
        $meta['TestSearchFixture']['indexes']['subject'] = 'unique';
        $this->migrate($meta);
        $this->assertStringContainsString('CREATE UNIQUE INDEX', $this->indexDefinition('subject'));
        $this->assertStringContainsString('USING btree (subject)', $this->indexDefinition('subject'));
    }

    public function testPartialSearchIndexIsReplacedByAFullOne()
    {
        (new Postgres())->createExtensions($this->db, array('pg_trgm'));
        $meta = $this->fixtureMeta(false);
        $this->migrate($meta);
        $this->db->execute('DROP INDEX "' . self::TABLE . '_subject"');
        $this->db->execute(
            'CREATE INDEX "' . self::TABLE . '_subject" ON "' . self::TABLE . '" USING gin ("subject" gin_trgm_ops)'
            . ' WHERE "description" IS NOT NULL'
        );

        $this->migrate($meta);

        $this->assertStringNotContainsString('WHERE', $this->indexDefinition('subject'));
        $this->assertStringContainsString('gin_trgm_ops', $this->indexDefinition('subject'));
    }

    public function testConstraintBackedUniqueIndexIsReplacedBySearchIndex()
    {
        (new Postgres())->createExtensions($this->db, array('pg_trgm'));
        $meta = $this->fixtureMeta(false);
        unset($meta['TestSearchFixture']['indexes']['subject']);
        $this->migrate($meta);
        $this->db->execute(
            'ALTER TABLE "' . self::TABLE . '" ADD CONSTRAINT "' . self::TABLE . '_subject" UNIQUE ("subject")'
        );

        $meta['TestSearchFixture']['indexes']['subject'] = 'gin_trgm';
        $this->migrate($meta);

        $this->assertStringContainsString('gin_trgm_ops', $this->indexDefinition('subject'));
        $constraint = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM pg_constraint WHERE conname = ?',
            \PDO::FETCH_ASSOC,
            array(self::TABLE . '_subject')
        );
        $this->assertSame(0, (int) $constraint['total']);
    }

    public function testUniqueIndexesAreDroppedWhenTheMetadataIndexIsPlain()
    {
        (new Postgres())->createExtensions($this->db, array('pg_trgm'));
        $meta = $this->fixtureMeta(false);
        $this->migrate($meta);
        $this->db->execute('CREATE UNIQUE INDEX "idx_legacy_description" ON "' . self::TABLE . '" ("description")');
        $this->db->execute(
            'ALTER TABLE "' . self::TABLE . '" ADD CONSTRAINT "' . self::TABLE . '_description" UNIQUE ("description")'
        );

        $meta['TestSearchFixture']['indexes']['description'] = 'INDEX';
        $this->migrate($meta);

        $unique = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM pg_index index_info
             JOIN pg_class table_class ON table_class.oid = index_info.indrelid
             WHERE table_class.relname = ? AND index_info.indisunique AND NOT index_info.indisprimary',
            \PDO::FETCH_ASSOC,
            array(self::TABLE)
        );
        $this->assertSame(0, (int) $unique['total']);
        $this->assertStringContainsString('btree (description)', $this->indexDefinition('description'));
        $this->assertStringNotContainsString('UNIQUE', $this->indexDefinition('description'));
    }

    public function testIndexNameUsedByAnotherTableIsReported()
    {
        (new Postgres())->createExtensions($this->db, array('pg_trgm'));
        $this->db->execute('CREATE TABLE "' . self::TABLE . '_other" ("subject" varchar(10))');
        $this->db->execute(
            'CREATE INDEX "' . self::TABLE . '_subject" ON "' . self::TABLE . '_other" ("subject")'
        );
        try {
            $this->migrate($this->fixtureMeta(false));
            $this->fail('An index name owned by another table was reused');
        } catch (\Gaia\Exception\MigrationDriver $e) {
            $this->assertStringContainsString('already used by table ' . self::TABLE . '_other', $e->getMessage());
        }
    }

    public function testCompositeIndexOnASearchColumnIsCreatedAfterIt()
    {
        (new Postgres())->createExtensions($this->db, array('pg_trgm'));
        $meta = $this->fixtureMeta(false);
        $meta['TestSearchFixture']['indexes']['subjectSearch'] = array(
            'type' => 'index',
            'columns' => array('id', 'searchVector'),
        );

        $this->migrate($meta);

        $this->assertStringContainsString('(id, "searchVector")', $this->indexDefinition('subjectSearch'));
    }

    /**
     * A model with a search block (agaia-4655) migrates with its system search columns and their indexes. On a
     * server without pgvector the block leaves out its semantic fields, which add the vector column.
     */
    public function testASearchBlockMigratesItsColumnsAndIndexes()
    {
        $skipped = (new Postgres())->createExtensions($this->db, array('vector', 'pg_trgm'));
        $this->assertNotContains('pg_trgm', $skipped);
        $withVector = !in_array('vector', $skipped, true);
        $model = $this->searchBlockModel();
        if (!$withVector) {
            unset($model['search']['semantic']);
        }
        $meta = $this->migrationMetaData('TestSearchFixture', $model);

        $this->migrate($meta);
        $this->migrate($meta);

        $this->assertSame('tsvector', $this->columnType('searchVector'));
        $this->assertSame('s', $this->column('searchVector')['attgenerated']);
        $this->assertStringContainsString('USING gin ("searchVector")', $this->indexDefinition('searchVector'));
        $this->assertStringContainsString('USING gin (subject gin_trgm_ops)', $this->indexDefinition('subject'));
        if (!$withVector) {
            $this->assertFalse($this->column('searchEmbedding'));
            return;
        }
        $this->assertSame('text', $this->columnType('searchSemanticText'));
        $this->assertSame('character varying(64)', $this->columnType('searchDesiredHash'));
        $this->assertSame('vector(768)', $this->columnType('searchEmbedding'));
        $this->assertSame('character varying(64)', $this->columnType('searchActualHash'));
        $this->assertStringStartsWith('timestamp', $this->columnType('searchEmbeddedAt'));
        $this->assertStringContainsString(
            'USING hnsw ("searchEmbedding" vector_cosine_ops)',
            $this->indexDefinition('searchEmbedding')
        );
    }

    public function testAnExistingTableWithTheOldHashColumnsEndsWithOnlyTheNewOnes()
    {
        $skipped = (new Postgres())->createExtensions($this->db, array('vector', 'pg_trgm'));
        if (in_array('vector', $skipped, true)) {
            $this->markTestSkipped('The vector extension is not available');
        }
        $meta = $this->migrationMetaData('TestSearchFixture', $this->searchBlockModel());
        $this->migrate($meta);
        // The table as it was before agaia-4747, with both hashes under their old names.
        $this->db->execute(
            'ALTER TABLE "' . self::TABLE . '" RENAME COLUMN "searchDesiredHash" TO "searchSemanticHash"'
        );
        $this->db->execute(
            'ALTER TABLE "' . self::TABLE . '" RENAME COLUMN "searchActualHash" TO "searchEmbeddingHash"'
        );

        $this->migrate($meta);
        $this->migrate($meta);

        $this->assertFalse($this->column('searchSemanticHash'));
        $this->assertFalse($this->column('searchEmbeddingHash'));
        $this->assertSame('character varying(64)', $this->columnType('searchDesiredHash'));
        $this->assertSame('character varying(64)', $this->columnType('searchActualHash'));
    }

    /**
     * Raw metadata of a fixture model with a search block that has semantic fields.
     *
     * @return array
     */
    private function searchBlockModel()
    {
        return array(
            'tableName' => self::TABLE,
            'fields' => array(
                'id' => array('name' => 'id', 'type' => 'char', 'length' => 36, 'null' => false),
                'subject' => array('name' => 'subject', 'type' => 'varchar', 'length' => 255, 'null' => true),
                'description' => array('name' => 'description', 'type' => 'text', 'null' => true),
            ),
            'indexes' => array('id' => 'primary'),
            'search' => array(
                'type' => 'fixture',
                'fts' => array('subject' => 'A', 'description' => 'B'),
                'fuzzy' => array('subject'),
                'semantic' => array('subject', 'description'),
                'display' => array('title' => 'subject'),
            ),
        );
    }

    /**
     * Fixture model metadata, shaped like a file in app/metadata/model.
     *
     * @param bool $withVector
     * @return array
     */
    private function fixtureMeta($withVector)
    {
        $fields = array(
            'id' => array('name' => 'id', 'type' => 'char', 'length' => 36, 'null' => false),
            'subject' => array('name' => 'subject', 'type' => 'varchar', 'length' => 255, 'null' => true),
            'description' => array('name' => 'description', 'type' => 'text', 'null' => true),
            'searchVector' => array(
                'name' => 'searchVector',
                'type' => 'tsvector',
                'null' => true,
                'generated' => array('sources' => array('subject' => 'A', 'description' => 'B')),
            ),
        );
        $indexes = array('id' => 'primary', 'searchVector' => 'gin', 'subject' => 'gin_trgm');
        if ($withVector) {
            $fields['embedding'] = array('name' => 'embedding', 'type' => 'vector', 'dimensions' => 3, 'null' => true);
            $indexes['embedding'] = 'hnsw_cosine';
        }

        return array('TestSearchFixture' => array(
            'tableName' => self::TABLE,
            'fields' => $fields,
            'indexes' => $indexes,
        ));
    }

    /**
     * Runs the Postgres migration steps for the fixture: create the table when it
     * is missing (as Migration::migrateTable does), otherwise sync it.
     *
     * @param array $meta
     * @return void
     */
    private function migrate(array $meta)
    {
        $handler = new Postgres();
        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('migrationHandler', $handler);
        $di->set('metaManager', new Manager($di));
        $prepare = new \ReflectionMethod(Migration::class, 'prepareTableDefinition');
        $definition = $prepare->invoke(new Migration($di), 'TestSearchFixture', $meta);
        $indexes = $meta['TestSearchFixture']['indexes'];
        // Same order as Migration::migrateTable: the preflight runs before any DDL.
        $handler->checkSearchSchema($this->db, self::TABLE, $definition['searchColumns'], $indexes);

        if ($handler->shouldSyncExistingTableOnly($this->db, self::TABLE)) {
            $handler->syncExistingTable(
                $this->db,
                self::TABLE,
                $definition['columns'],
                $indexes,
                $definition['searchColumns']
            );
            return;
        }

        $this->db->createTable(self::TABLE, 'public', array(
            'columns' => $definition['columns'],
            'indexes' => $definition['indexes'],
        ));
        $handler->syncSearchSchema($this->db, self::TABLE, $definition['searchColumns'], $indexes);
    }

    /**
     * The metadata Migration reads for a model whose file holds the given raw metadata: the production path that
     * applies the search block.
     *
     * @param string $model
     * @param array $raw
     * @return array
     */
    private function migrationMetaData($model, array $raw)
    {
        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('fileHandler', new class (array($model => $raw)) {
            private $meta;

            public function __construct(array $meta)
            {
                $this->meta = $meta;
            }

            public function readFile($path)
            {
                return $this->meta;
            }
        });
        $read = new \ReflectionMethod(Migration::class, 'getMetaData');

        return $read->invoke(new Migration($di), $model);
    }

    private function tableExists()
    {
        $row = $this->db->fetchOne(
            "SELECT to_regclass('public." . self::TABLE . "') IS NOT NULL AS present",
            \PDO::FETCH_ASSOC
        );

        return in_array($row['present'], array(true, 't', 1, '1'), true);
    }

    private function column($name)
    {
        return $this->db->fetchOne(
            "SELECT attnum, attgenerated FROM pg_attribute
             WHERE attrelid = 'public." . self::TABLE . "'::regclass AND attname = ? AND NOT attisdropped",
            \PDO::FETCH_ASSOC,
            array($name)
        );
    }

    private function columnType($name)
    {
        $row = $this->db->fetchOne(
            "SELECT format_type(atttypid, atttypmod) AS type FROM pg_attribute
             WHERE attrelid = 'public." . self::TABLE . "'::regclass AND attname = ? AND NOT attisdropped",
            \PDO::FETCH_ASSOC,
            array($name)
        );

        return $row['type'];
    }

    private function indexDefinition($column)
    {
        $row = $this->db->fetchOne(
            'SELECT indexdef FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ?',
            \PDO::FETCH_ASSOC,
            array('public', self::TABLE, self::TABLE . '_' . $column)
        );

        return empty($row) ? '' : $row['indexdef'];
    }

    private function extensionInstalled($name)
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM pg_extension WHERE extname = ?',
            \PDO::FETCH_ASSOC,
            array($name)
        );

        return (int) $row['total'] === 1;
    }

    private function dropFixture()
    {
        $this->db->execute('DROP TABLE IF EXISTS "' . self::TABLE . '_other"');
        $this->db->execute('DROP TABLE IF EXISTS "' . self::TABLE . '"');
    }
}
