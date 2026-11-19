<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Db\Migration\Postgres;
use Gaia\Db\Migration\SchemaConnection;
use Gaia\Libraries\Meta\Manager;
use Gaia\Libraries\Meta\Migration;
use Gaia\Libraries\Meta\Migration\Plan;
use Gaia\Libraries\Meta\Migration\PlanningConnection;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Column;
use Phalcon\Db\Index;
use Phalcon\Db\Dialect\Postgresql;
use PHPUnit\Framework\TestCase;

/**
 * Runs Migration::up() on a PlanningConnection over a fake adapter and checks that the
 * statements migrate would run are collected in the plan, with their table, and that
 * none reaches the adapter (agaia-4688: migrate --dry-run).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MigrationDryRunTest extends TestCase
{
    private const MODEL = 'TestDryRunFixture';
    private const TABLE = 'test_dry_run_fixtures';

    public function testMissingTableIsPlannedNotCreated()
    {
        $plan = $this->plan($this->fakeAdapter(false, array()));

        $sql = $this->sqlOf($plan);
        $this->assertNotEmpty($sql);
        $this->assertStringStartsWith('CREATE TABLE "public"."' . self::TABLE . '"', $sql[0]);
        $this->assertStringContainsString('"name" CHARACTER VARYING(255) NULL', $sql[0]);
        $this->assertContains(
            'CREATE INDEX "' . self::TABLE . '_byName" ON "public"."' . self::TABLE . '" ("name")',
            $sql
        );
        $this->assertTrue($plan->hasChanges());
        $this->assertSame(array(self::TABLE), array_values(array_unique(array_column($plan->changes(), 'table'))));
    }

    public function testFieldRemovedFromMetadataIsPlannedAsADropNotRun()
    {
        $existing = array(
            new Column('id', array('type' => Column::TYPE_CHAR, 'size' => 36, 'notNull' => true)),
            new Column('name', array('type' => Column::TYPE_VARCHAR, 'size' => 255)),
            new Column('legacy', array('type' => Column::TYPE_VARCHAR, 'size' => 10)),
        );

        $plan = $this->plan($this->fakeAdapter(true, $existing, array(new Index(self::TABLE . '_byName', array('name')))));

        $this->assertSame(
            array('ALTER TABLE "' . self::TABLE . '" DROP COLUMN IF EXISTS "legacy" CASCADE'),
            $this->sqlOf($plan)
        );
    }

    public function testTableInStepWithMetadataPlansNothing()
    {
        $existing = array(
            new Column('id', array('type' => Column::TYPE_CHAR, 'size' => 36, 'notNull' => true)),
            new Column('name', array('type' => Column::TYPE_VARCHAR, 'size' => 255)),
        );

        $plan = $this->plan($this->fakeAdapter(true, $existing, array(new Index(self::TABLE . '_byName', array('name')))));

        $this->assertSame(array(), $plan->changes());
        $this->assertFalse($plan->hasChanges());
    }

    public function testRoutineStatementsAreListedButNotCounted()
    {
        $plan = new Plan();
        $plan->forTable('a_view', true);
        $plan->add('DROP VIEW IF EXISTS "a_view" CASCADE');
        $plan->forTable('a_table');
        $plan->add("  \n");

        $this->assertFalse($plan->hasChanges());
        $this->assertSame(array('a_view'), array_column($plan->routine(), 'table'));
    }

    public function testTransactionsAreNotOpened()
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->never())->method('begin');
        $adapter->expects($this->never())->method('commit');
        $adapter->expects($this->never())->method('rollback');
        $connection = new PlanningConnection($adapter, new Plan());

        $this->assertTrue($connection->begin());
        $this->assertTrue($connection->commit());
        $this->assertTrue($connection->rollback());
    }

    /**
     * agaia-4705: the planning connection is Gaia's SchemaConnection, not Phalcon's
     * AdapterInterface, whose signatures differ between Phalcon 4 and 5; so it has no
     * data writes or raw handle to guard either.
     */
    public function testThePlanningConnectionDoesNotImplementPhalconsAdapter()
    {
        $connection = new PlanningConnection($this->createMock(AdapterInterface::class), new Plan());

        $this->assertInstanceOf(SchemaConnection::class, $connection);
        $this->assertNotInstanceOf(AdapterInterface::class, $connection);
        $this->assertFalse(method_exists($connection, 'insert'));
        $this->assertFalse(method_exists($connection, 'getInternalHandler'));
    }

    public function testDdlHelpersArePlannedWithTheAdaptersDialect()
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('getDialect')->willReturn(new Postgresql());
        $adapter->expects($this->never())->method('addColumn');
        $plan = new Plan();
        $plan->forTable(self::TABLE);
        $connection = new PlanningConnection($adapter, $plan);

        $column = new Column('extra', array('type' => Column::TYPE_VARCHAR, 'size' => 10));

        $connection->addColumn(self::TABLE, null, $column);

        // The dialect's exact text differs between Phalcon versions; the plan holds what it renders.
        $this->assertSame(array((new Postgresql())->addColumn(self::TABLE, '', $column)), $this->sqlOf($plan));
        $this->assertStringStartsWith(
            'ALTER TABLE "' . self::TABLE . '" ADD COLUMN "extra" CHARACTER VARYING(10)',
            $this->sqlOf($plan)[0]
        );
    }

    public function testAWriteThroughAReadIsRejected()
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->never())->method('fetchAll');
        $connection = new PlanningConnection($adapter, new Plan());

        $this->expectException(\LogicException::class);
        $connection->fetchAll('UPDATE "users" SET "name" = \'x\'');
    }

    public function testAPlannedStatementCannotCarryPlaceholders()
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $connection = new PlanningConnection($adapter, new Plan());

        $this->expectException(\LogicException::class);
        $connection->execute('DELETE FROM "users" WHERE "id" = ?', array(1));
    }

    public function testPlannedExtensionCountsAsInstalledInADryRun()
    {
        $plan = new Plan();
        $handler = new Postgres();

        // pg_available_extensions has vector (fetchOne total 1), pg_extension has not (total 0, so only the plan can say it is there)
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchOne')->willReturnCallback(function ($sql) {
            if (strpos($sql, 'pg_extension') === false && strpos($sql, 'pg_available_extensions') === false) {
                return array();
            }
            return array('total' => strpos($sql, 'pg_available_extensions') !== false ? 1 : 0);
        });
        $adapter->method('escapeIdentifier')->willReturnCallback(function ($identifier) {
            return '"' . $identifier . '"';
        });
        $connection = new PlanningConnection($adapter, $plan);

        $handler->createExtensions($connection, array('vector'));
        $this->assertContains('CREATE EXTENSION IF NOT EXISTS "vector"', $this->sqlOf($plan));

        // does not throw "needs the PostgreSQL extension vector"
        $handler->checkSearchSchema($connection, 'issues', array('embedding' => 'vector(3)'), array());
        $this->addToAssertionCount(1);
    }

    public function testExtensionNotPlannedStillFailsTheCheck()
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchOne')->willReturn(array('total' => 0));
        $connection = new PlanningConnection($adapter, new Plan());

        $this->expectException(\Gaia\Exception\MigrationDriver::class);
        (new Postgres())->checkSearchSchema($connection, 'issues', array('embedding' => 'vector(3)'), array());
    }

    public function testReadsGoToTheDatabase()
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('fetchAll')->willReturn(array(array('a' => 1)));
        $connection = new PlanningConnection($adapter, new Plan());

        $this->assertSame(array(array('a' => 1)), $connection->fetchAll('  select 1'));
    }

    /**
     * Runs Migration::up() for the fixture model on a PlanningConnection over the adapter.
     *
     * @param AdapterInterface $adapter
     * @return Plan
     */
    private function plan(AdapterInterface $adapter)
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

        $plan = new Plan();
        $plan->forTable(self::TABLE);
        $migration = new Migration($di);
        $migration->setConnection(new PlanningConnection($adapter, $plan));
        $migration->up(self::MODEL);
        return $plan;
    }

    /**
     * A fake adapter that answers the reads and fails the test on any write.
     *
     * @param bool $tableExists
     * @param Column[] $columns The table's current columns
     * @param Index[] $indexes The table's current indexes
     * @return AdapterInterface
     */
    private function fakeAdapter($tableExists, array $columns, array $indexes = array())
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('tableExists')->willReturn($tableExists);
        $adapter->method('describeColumns')->willReturn($columns);
        $adapter->method('describeIndexes')->willReturn($indexes);
        $adapter->method('fetchOne')->willReturn(array());
        $adapter->method('getDialect')->willReturn(new Postgresql());
        $adapter->method('escapeIdentifier')->willReturnCallback(function ($identifier) {
            return '"' . $identifier . '"';
        });
        foreach (array('execute', 'createTable', 'addColumn', 'modifyColumn', 'dropColumn', 'addIndex',
            'dropIndex', 'begin', 'commit') as $write) {
            $adapter->expects($this->never())->method($write);
        }
        return $adapter;
    }

    /**
     * @param Plan $plan
     * @return string[] The planned statements' SQL
     */
    private function sqlOf(Plan $plan)
    {
        return array_column($plan->changes(), 'sql');
    }

    /**
     * The fixture model metadata: a key and a varchar with an index.
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
            ),
            'indexes' => array(
                'id' => 'primary',
                'byName' => array('type' => 'index', 'columns' => array('name')),
            ),
        );
    }
}
