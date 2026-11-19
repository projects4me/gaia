<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Db\Migration\AdapterConnection;
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Db\Column;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the connection a real migration runs on passes its calls to the Phalcon
 * adapter with arguments both Phalcon 4 and Phalcon 5 accept: bound parameters as an
 * array (never null) and the schema as a string (agaia-4705).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AdapterConnectionTest extends TestCase
{
    public function testWritesRunOnTheAdapterWithArrayPlaceholders()
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('execute')
            ->with('DROP VIEW IF EXISTS "v"', array())
            ->willReturn(true);

        $this->assertTrue((new AdapterConnection($adapter))->execute('DROP VIEW IF EXISTS "v"'));
    }

    public function testReadsPassTheirPlaceholders()
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('fetchAll')
            ->with('SELECT 1 WHERE ? = ?', \PDO::FETCH_ASSOC, array(1, 1))
            ->willReturn(array(array('?column?' => 1)));

        $rows = (new AdapterConnection($adapter))->fetchAll('SELECT 1 WHERE ? = ?', \PDO::FETCH_ASSOC, array(1, 1));

        $this->assertSame(array(array('?column?' => 1)), $rows);
    }

    public function testANullSchemaIsPassedAsAString()
    {
        $column = new Column('extra', array('type' => Column::TYPE_VARCHAR, 'size' => 10));
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->once())->method('addColumn')->with('t', '', $column)->willReturn(true);

        $this->assertTrue((new AdapterConnection($adapter))->addColumn('t', null, $column));
    }
}
