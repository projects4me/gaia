<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Db\Migration\AdapterConnection;
use Gaia\Db\Migration\Postgres;
use Gaia\Libraries\Meta\Manager;
use Gaia\Libraries\Meta\Migration;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Column;
use Phalcon\Db\Dialect\Postgresql;
use Phalcon\Db\Index;
use PHPUnit\Framework\TestCase;

/**
 * Migrates a model whose table does not exist yet on a fake adapter and checks that
 * Migration creates it with the adapter's createTable(), with the columns, indexes and
 * options from the metadata (agaia-4687: no phalcon/migrations morphTable).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MigrationCreateTableTest extends TestCase
{
    private const MODEL = 'TestCreateFixture';
    private const TABLE = 'test_create_fixtures';

    /**
     * The createTable() calls the fake adapter received: [tableName, schemaName, definition]
     *
     * @var array
     */
    private $created = array();

    public function testMissingTableIsCreatedWithTheMetadataDefinition()
    {
        $this->migrate();

        $this->assertCount(1, $this->created, 'createTable() runs once for a missing table');
        list($tableName, $schemaName, $definition) = $this->created[0];

        $this->assertSame(self::TABLE, $tableName);
        $this->assertSame('public', $schemaName);
        $this->assertSame(array(), $definition['options']);

        $columns = array();
        foreach ($definition['columns'] as $column) {
            $this->assertInstanceOf(Column::class, $column);
            $columns[$column->getName()] = $column;
        }
        $this->assertSame(array('id', 'name', 'issueNumber', 'dateCreated'), array_keys($columns));
        $this->assertSame(36, (int) $columns['id']->getSize());
        $this->assertTrue($columns['id']->isNotNull());
        // Postgres declares the key on the column, not as a PRIMARY index.
        $this->assertTrue($columns['id']->isPrimary());
        $this->assertSame(255, (int) $columns['name']->getSize());
        $this->assertFalse($columns['name']->isNotNull());
        $this->assertTrue($columns['issueNumber']->isAutoIncrement());

        $indexes = array();
        foreach ($definition['indexes'] as $index) {
            $this->assertInstanceOf(Index::class, $index);
            $indexes[$index->getName()] = array($index->getColumns(), $index->getType());
        }
        $this->assertSame(
            array(
                self::TABLE . '_name' => array(array('name'), 'UNIQUE'),
                self::TABLE . '_dateCreated' => array(array('dateCreated'), ''),
                self::TABLE . '_byNameDate' => array(array('name', 'dateCreated'), ''),
            ),
            $indexes
        );
    }

    public function testCreatedTableRendersTheExpectedPostgresDdl()
    {
        $this->migrate();
        list($tableName, $schemaName, $definition) = $this->created[0];

        $sql = (new Postgresql())->createTable($tableName, $schemaName, $definition);

        $this->assertStringContainsString('CREATE TABLE "public"."' . self::TABLE . '"', $sql);
        $this->assertStringContainsString('"id" CHARACTER(36) NOT NULL', $sql);
        $this->assertStringContainsString('"name" CHARACTER VARYING(255) NULL', $sql);
        $this->assertStringContainsString('"issueNumber" SERIAL NOT NULL', $sql);
        $this->assertStringContainsString('PRIMARY KEY ("id")', $sql);
        $this->assertStringContainsString('CONSTRAINT "' . self::TABLE . '_name" UNIQUE ("name")', $sql);
        $this->assertStringContainsString(
            'CREATE INDEX "' . self::TABLE . '_byNameDate" ON "public"."' . self::TABLE . '" ("name", "dateCreated")',
            $sql
        );
    }

    /**
     * Runs Migration::up() for the fixture model on a fake adapter where the table is missing.
     *
     * @return void
     */
    private function migrate()
    {
        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('migrationHandler', new Postgres());
        $di->set('metaManager', new Manager($di));
        $di->set('fileHandler', new class (array(self::MODEL => $this->fixture())) {
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

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('tableExists')->willReturn(false);
        $connection->method('createTable')->willReturnCallback(function ($tableName, $schemaName, $definition) {
            $this->created[] = array($tableName, $schemaName, $definition);
            return true;
        });

        $migration = new Migration($di);
        $migration->setConnection(new AdapterConnection($connection));
        $migration->up(self::MODEL);
    }

    /**
     * The fixture model metadata: a key, a nullable unique varchar, a serial and a date,
     * with single and multi-column indexes.
     *
     * @return array
     */
    private function fixture()
    {
        return array(
            'tableName' => self::TABLE,
            'fields' => array(
                'id' => array('name' => 'id', 'type' => 'char', 'length' => 36, 'null' => false),
                'name' => array('name' => 'name', 'type' => 'varchar', 'length' => 255, 'null' => true),
                'issueNumber' => array(
                    'name' => 'issueNumber',
                    'type' => 'int',
                    'null' => false,
                    'autoIncrement' => true,
                ),
                'dateCreated' => array('name' => 'dateCreated', 'type' => 'datetime', 'null' => true),
            ),
            'indexes' => array(
                'id' => 'primary',
                'name' => 'unique',
                'dateCreated' => 'index',
                'byNameDate' => array('type' => 'index', 'columns' => array('name', 'dateCreated')),
            ),
        );
    }
}
