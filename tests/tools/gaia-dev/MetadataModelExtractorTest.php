<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\MetadataModelExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the metadata model extractor (agaia-4128).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MetadataModelExtractorTest extends TestCase
{
    /**
     * @return array[] records keyed by model name
     */
    private function extract(): array
    {
        $records = (new MetadataModelExtractor(__DIR__ . '/fixtures/repo'))->extract();

        return array_column($records, null, 'name');
    }

    public function testExtractsOneRecordPerModelSortedByName()
    {
        $records = (new MetadataModelExtractor(__DIR__ . '/fixtures/repo'))->extract();

        $this->assertSame(['Tag', 'Widget'], array_column($records, 'name'));
        $this->assertSame('app/metadata/model/Widget.php', $records[1]['file']);
        $this->assertSame('widgets', $records[1]['tableName']);
        $this->assertFalse($records[1]['isView']);
        $this->assertTrue($records[0]['isView']);
    }

    public function testFieldsKeepTheirAttributesAndAclFlag()
    {
        $fields = array_column($this->extract()['Widget']['fields'], 'attributes', 'name');

        $this->assertSame(['id', 'ownerId', 'ownerName', 'secret'], array_keys($fields));
        $this->assertSame('ownerId', $fields['ownerName']['linkedTo']);
        $this->assertFalse($fields['secret']['acl']);
        $this->assertArrayNotHasKey('name', $fields['id']);
    }

    public function testRelationshipsResolveModelNamesFromClassNames()
    {
        $relationships = array_column($this->extract()['Widget']['relationships'], null, 'name');

        $this->assertSame('hasOne', $relationships['owner']['kind']);
        $this->assertSame('User', $relationships['owner']['relatedModel']);
        $this->assertNull($relationships['owner']['secondaryModel']);
        $this->assertSame('ownerId', $relationships['owner']['attributes']['primaryKey']);

        $this->assertSame('hasManyToMany', $relationships['tags']['kind']);
        $this->assertSame('Widgettag', $relationships['tags']['relatedModel']);
        $this->assertSame('Tag', $relationships['tags']['secondaryModel']);
        $this->assertArrayNotHasKey('relatedModel', $relationships['tags']['attributes']);
    }

    public function testBehavioursAndAcl()
    {
        $records = $this->extract();

        $this->assertSame(['auditBehavior', 'softDeleteBehavior'], $records['Widget']['behaviors']);
        $this->assertSame(['Project'], $records['Widget']['acl']['groups']);
        $this->assertSame([], $records['Tag']['behaviors']);
        $this->assertNull($records['Tag']['acl']);
    }

    public function testReadsEveryMetadataFileOfTheRepository()
    {
        $root = dirname(__DIR__, 3);
        $files = glob($root . '/app/metadata/model/*.php');
        $records = (new MetadataModelExtractor($root))->extract();

        $this->assertCount(count($files), $records);
    }
}
