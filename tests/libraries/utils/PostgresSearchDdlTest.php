<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Db\Migration\PostgresSearchDdl;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the DDL of vector columns, generated tsvector columns and
 * gin / gin_trgm / hnsw_cosine indexes (agaia-4654).
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PostgresSearchDdlTest extends TestCase
{
    private function fields()
    {
        return array(
            'subject' => array('name' => 'subject', 'type' => 'varchar', 'length' => 255, 'null' => false),
            'description' => array('name' => 'description', 'type' => 'text', 'null' => true),
            'dateCreated' => array('name' => 'dateCreated', 'type' => 'datetime', 'null' => true),
        );
    }

    public function testRecognisesSearchTypesAndIndexKinds()
    {
        $this->assertTrue(PostgresSearchDdl::isSearchType('vector'));
        $this->assertTrue(PostgresSearchDdl::isSearchType('tsvector'));
        $this->assertFalse(PostgresSearchDdl::isSearchType('text'));

        $this->assertTrue(PostgresSearchDdl::isSearchIndexKind('gin'));
        $this->assertTrue(PostgresSearchDdl::isSearchIndexKind('gin_trgm'));
        $this->assertTrue(PostgresSearchDdl::isSearchIndexKind('hnsw_cosine'));
        $this->assertFalse(PostgresSearchDdl::isSearchIndexKind('index'));
        $this->assertFalse(PostgresSearchDdl::isSearchIndexKind(array('type' => 'unique', 'columns' => array('a'))));
    }

    public function testVectorFieldMapsToVectorWithDimensions()
    {
        $sql = (new PostgresSearchDdl())->columnDefinition(
            array('name' => 'embedding', 'type' => 'vector', 'dimensions' => 1536, 'null' => true),
            $this->fields()
        );

        $this->assertSame('vector(1536)', $sql);
    }

    public function testVectorFieldWithoutDimensionsIsRejected()
    {
        $this->expectException(\Gaia\Exception\MigrationDriver::class);

        (new PostgresSearchDdl())->columnDefinition(
            array('name' => 'embedding', 'type' => 'vector', 'null' => true),
            $this->fields()
        );
    }

    public function testTsvectorFieldIsAStoredGeneratedColumnOfWeightedSources()
    {
        $sql = (new PostgresSearchDdl())->columnDefinition(
            array(
                'name' => 'searchVector',
                'type' => 'tsvector',
                'null' => true,
                'generated' => array('sources' => array('subject' => 'A', 'description' => 'B')),
            ),
            $this->fields()
        );

        $this->assertSame(
            "tsvector GENERATED ALWAYS AS ("
            . "setweight(to_tsvector('english'::regconfig, coalesce(\"subject\"::text, '')), 'A')"
            . " || setweight(to_tsvector('english'::regconfig, coalesce(\"description\"::text, '')), 'B')"
            . ") STORED",
            $sql
        );
    }

    public function testTsvectorFieldUsesTheGivenTextSearchConfig()
    {
        $sql = (new PostgresSearchDdl())->columnDefinition(
            array(
                'name' => 'searchVector',
                'type' => 'tsvector',
                'null' => true,
                'generated' => array('config' => 'simple', 'sources' => array('subject' => 'A')),
            ),
            $this->fields()
        );

        $this->assertStringContainsString("to_tsvector('simple'::regconfig", $sql);
    }

    /**
     * @dataProvider invalidTsvectorProvider
     */
    public function testInvalidTsvectorDefinitionsAreRejected(array $generated)
    {
        $this->expectException(\Gaia\Exception\MigrationDriver::class);

        (new PostgresSearchDdl())->columnDefinition(
            array('name' => 'searchVector', 'type' => 'tsvector', 'null' => true, 'generated' => $generated),
            $this->fields()
        );
    }

    public function invalidTsvectorProvider()
    {
        return array(
            'no sources' => array(array()),
            'unknown source field' => array(array('sources' => array('missing' => 'A'))),
            'bad weight' => array(array('sources' => array('subject' => 'E'))),
            'non-text source' => array(array('sources' => array('dateCreated' => 'A'))),
            'injected config' => array(array('config' => "english'); DROP TABLE x; --", 'sources' => array('subject' => 'A'))),
        );
    }

    public function testIndexKindsBuildTheirAccessMethodAndOperatorClass()
    {
        $ddl = new PostgresSearchDdl();

        $this->assertSame(
            'CREATE INDEX IF NOT EXISTS "fixtures_searchVector" ON "fixtures" USING gin ("searchVector")',
            $ddl->createIndexSql('fixtures', 'searchVector', 'gin')
        );
        $this->assertSame(
            'CREATE INDEX IF NOT EXISTS "fixtures_subject" ON "fixtures" USING gin ("subject" gin_trgm_ops)',
            $ddl->createIndexSql('fixtures', 'subject', 'gin_trgm')
        );
        $this->assertSame(
            'CREATE INDEX IF NOT EXISTS "fixtures_embedding" ON "fixtures" USING hnsw ("embedding" vector_cosine_ops)',
            $ddl->createIndexSql('fixtures', 'embedding', 'hnsw_cosine')
        );
    }

    public function testSignatureChangesWithTheDefinition()
    {
        $ddl = new PostgresSearchDdl();

        $this->assertSame($ddl->signature('vector(3)'), $ddl->signature('vector(3)'));
        $this->assertNotSame($ddl->signature('vector(3)'), $ddl->signature('vector(4)'));
    }

    public function testIndexDefinitionsMatchOnlyTheirKind()
    {
        $ddl = new PostgresSearchDdl();
        $btree = 'CREATE INDEX fixtures_subject ON public.fixtures USING btree (subject)';
        $gin = 'CREATE INDEX "fixtures_searchVector" ON public.fixtures USING gin ("searchVector")';
        $trigram = 'CREATE INDEX fixtures_subject ON public.fixtures USING gin (subject gin_trgm_ops)';
        $hnsw = 'CREATE INDEX fixtures_embedding ON public.fixtures USING hnsw (embedding vector_cosine_ops)';

        $this->assertTrue($ddl->indexMatchesKind($gin, 'gin'));
        $this->assertTrue($ddl->indexMatchesKind($trigram, 'gin_trgm'));
        $this->assertTrue($ddl->indexMatchesKind($hnsw, 'hnsw_cosine'));
        $this->assertFalse($ddl->indexMatchesKind($btree, 'gin_trgm'));
        $this->assertFalse($ddl->indexMatchesKind($trigram, 'gin'));
        $this->assertFalse($ddl->indexMatchesKind($gin, 'gin_trgm'));
    }

    public function testRequiredExtensions()
    {
        $this->assertSame('vector', PostgresSearchDdl::requiredExtension('vector(3)'));
        $this->assertSame('vector', PostgresSearchDdl::requiredExtension('hnsw_cosine'));
        $this->assertSame('pg_trgm', PostgresSearchDdl::requiredExtension('gin_trgm'));
        $this->assertNull(PostgresSearchDdl::requiredExtension('gin'));
        $this->assertNull(PostgresSearchDdl::requiredExtension('tsvector GENERATED ALWAYS AS (x) STORED'));
    }
}
