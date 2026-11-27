<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Meta\Manager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Manager::parseFields and getModelMeta on a REST module that has a controller
 * but no model metadata (Issueplanning, Milestoneoverview). The ACL catalog
 * reads every module while the config loads, so it must not warn (agaia-4074).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MetaManagerParseFieldsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
    }

    public function testMetadataWithoutFieldsParsesToNoAttributes()
    {
        $manager = new Manager(new \Phalcon\Di\FactoryDefault());

        $data = $manager->parseFields(null);

        $this->assertSame(array(), $data['attributes']);
        $this->assertSame(array(), $data['notNull']);
        $this->assertSame(array(), $data['default']);
        $this->assertFalse($data['id']);
        $this->assertNull($data['tableName']);
    }

    public function testModelMetaPassesTheMentionsBlockThrough()
    {
        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('fileHandler', new class {
            public function readFile($path)
            {
                return array('Agaia4680Model' => array(
                    'table' => 'agaia4680',
                    'fields' => array(),
                    'mentions' => array('fields' => array('comment')),
                ));
            }
        });
        $manager = new Manager($di);

        $meta = $manager->getModelMeta('Agaia4680Model');

        $this->assertSame(array('fields' => array('comment')), $meta['mentions']);
    }

    public function testModelMetaForMissingMetadataFileHasNoFields()
    {
        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('fileHandler', new class {
            public function readFile($path)
            {
                return null;
            }
        });
        $manager = new Manager($di);

        $meta = $manager->getModelMeta('Agaia4074MissingModel');

        $this->assertSame(array(), $meta['fields']);
        $this->assertNull($meta['tableName']);
        $this->assertSame(array(), $meta['relationships']);
    }

    /**
     * The fields of a table with a GUID key, a sequence-filled number and columns with and without a default.
     *
     * @return array
     */
    private function ormMetadata()
    {
        return array(
            'tableName' => 'agaia4968',
            'fields' => array(
                'id' => array('name' => 'id', 'type' => 'char', 'length' => 36, 'null' => false),
                'number' => array('name' => 'number', 'type' => 'int', 'autoIncrement' => 'true', 'null' => true),
                'name' => array('name' => 'name', 'type' => 'varchar', 'length' => 50, 'null' => false),
                'status' => array('name' => 'status', 'type' => 'varchar', 'null' => false, 'default' => 'open'),
                'deleted' => array('name' => 'deleted', 'type' => 'bool', 'null' => false, 'default' => 0),
                'estimate' => array('name' => 'estimate', 'type' => 'float', 'null' => true),
                'dateCreated' => array('name' => 'dateCreated', 'type' => 'datetime', 'null' => true),
            ),
            'indexes' => array('id' => 'primary'),
        );
    }

    public function testTheIdentityColumnIsTheAutoIncrementOneNotTheGuidKey()
    {
        $data = (new Manager(new \Phalcon\Di\FactoryDefault()))->parseFields($this->ormMetadata());

        $this->assertSame('number', $data['id']);
        $this->assertSame(array('id'), $data['primary']);
        $this->assertContains('number', $data['notNull'], 'PostgreSQL makes a SERIAL column NOT NULL');
    }

    public function testNotNullColumnsWithADefaultAndNullableColumnsHaveDefaultValues()
    {
        $data = (new Manager(new \Phalcon\Di\FactoryDefault()))->parseFields($this->ormMetadata());

        $this->assertSame(
            array('status' => 'open', 'deleted' => 0, 'estimate' => null, 'dateCreated' => null),
            $data['default']
        );
    }

    public function testNumericColumnsAreMarked()
    {
        $data = (new Manager(new \Phalcon\Di\FactoryDefault()))->parseFields($this->ormMetadata());

        $this->assertSame(array('number' => true, 'estimate' => true), $data['numeric']);
    }

    public function testABoolStoredAsSmallintIsTypedBoundAndCastAsASmallint()
    {
        $metadata = $this->ormMetadata();
        $metadata['fields']['deleted']['storage'] = 'smallint';

        $data = (new Manager(new \Phalcon\Di\FactoryDefault()))->parseFields($metadata);

        $this->assertSame(\Phalcon\Db\Column::TYPE_SMALLINTEGER, $data['type']['deleted']);
        $this->assertSame(\Phalcon\Db\Column::BIND_PARAM_INT, $data['bind']['deleted']);
        $this->assertArrayHasKey('deleted', $data['numeric']);
    }

    public function testABoolWithoutStorageStaysABoolean()
    {
        $data = (new Manager(new \Phalcon\Di\FactoryDefault()))->parseFields($this->ormMetadata());

        $this->assertSame(\Phalcon\Db\Column::TYPE_BOOLEAN, $data['type']['deleted']);
        $this->assertSame(\Phalcon\Db\Column::BIND_PARAM_BOOL, $data['bind']['deleted']);
    }

    public function testTextColumnsAreBoundAsStrings()
    {
        $manager = new Manager(new \Phalcon\Di\FactoryDefault());

        foreach (array('text', 'tinytext', 'mediumtext', 'longtext') as $type) {
            $this->assertSame(\Phalcon\Db\Column::BIND_PARAM_STR, $manager->getBindType($type), $type);
        }
    }

    public function testOrmMetadataHasOnlyThePhalconEntriesWithTheStoredColumnTypes()
    {
        $di = new \Phalcon\Di\FactoryDefault();
        $metadata = $this->ormMetadata();
        $di->set('fileHandler', new class ($metadata) {
            private $metadata;

            public function __construct($metadata)
            {
                $this->metadata = $metadata;
            }

            public function readFile($path)
            {
                return array('Agaia4968Model' => $this->metadata);
            }
        });
        $di->set('migrationHandler', new \Gaia\Db\Migration\Postgres());

        $orm = (new Manager($di))->getOrmMetaData('Agaia4968Model');

        $this->assertSame(array(), array_filter(array_keys($orm), 'is_string'));
        $this->assertSame(\Phalcon\Db\Column::TYPE_TIMESTAMP, $orm[\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES]['dateCreated']);
        $this->assertSame(\Phalcon\Db\Column::TYPE_VARCHAR, $orm[\Phalcon\Mvc\Model\MetaData::MODELS_DATA_TYPES]['name']);
        $this->assertSame('number', $orm[\Phalcon\Mvc\Model\MetaData::MODELS_IDENTITY_COLUMN]);
    }
}
