<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Search\SemanticRecords;
use PHPUnit\Framework\TestCase;

/**
 * The pending query (agaia-4764) against the test database: it returns the non-deleted records whose desired hash is
 * set and whose actual hash is null or differs, by id after the cursor. The fixture table is dropped afterwards.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SemanticRecordsPendingTest extends TestCase
{
    private const TABLE = 'test_semantic_pending';

    /**
     * @var \Phalcon\Db\Adapter\AdapterInterface
     */
    private $db;

    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class) || \Phalcon\Di\Di::getDefault() === null) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
        $db = \Phalcon\Di\Di::getDefault()->getShared('db');
        if ($db->getType() !== 'pgsql') {
            $this->markTestSkipped('The pending query is written for PostgreSQL');
        }
        $this->db = $db;
        $this->db->execute('DROP TABLE IF EXISTS "' . self::TABLE . '"');
        $this->db->execute(
            'CREATE TABLE "' . self::TABLE . '" ("id" varchar(36) PRIMARY KEY, "deleted" smallint NOT NULL DEFAULT 0, '
            . '"searchDesiredHash" varchar(64), "searchActualHash" varchar(64))'
        );
        $rows = [
            // never embedded
            ['a', 0, 'h1', null],
            // embedded from another text
            ['b', 0, 'h2', 'old'],
            // up to date
            ['c', 0, 'h3', 'h3'],
            // stale but deleted
            ['d', 1, 'h4', null],
            // no text yet: the backfill has not reached it, so there is nothing to embed
            ['e', 0, null, null],
            ['f', 0, 'h6', null],
        ];
        foreach ($rows as $row) {
            $this->db->execute(
                'INSERT INTO "' . self::TABLE . '" ("id", "deleted", "searchDesiredHash", "searchActualHash") '
                . 'VALUES (?, ?, ?, ?)',
                $row
            );
        }
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->db->execute('DROP TABLE IF EXISTS "' . self::TABLE . '"');
        }
    }

    public function testItReturnsMissingAndStaleRecordsAndSkipsCurrentDeletedAndTextlessOnes()
    {
        $this->assertSame(['a', 'b', 'f'], (new SemanticRecords($this->db))->pending(self::TABLE, '', 10));
    }

    public function testItPagesByIdAfterTheCursor()
    {
        $records = new SemanticRecords($this->db);

        $this->assertSame(['a', 'b'], $records->pending(self::TABLE, '', 2));
        $this->assertSame(['f'], $records->pending(self::TABLE, 'b', 2));
        $this->assertSame([], $records->pending(self::TABLE, 'f', 2));
    }
}
