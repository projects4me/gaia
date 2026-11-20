<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Meta\Migration\Driver;
use Gaia\Db\Migration\SchemaConnection;
use Phalcon\Db\Exception as DbException;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Model\Exception as ModelException;
use PHPUnit\Framework\TestCase;

/**
 * The migration driver turns the database and model exceptions a migration throws
 * into a Gaia MigrationDriver exception (agaia-4700).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MigrationDriverErrorTest extends TestCase
{
    public static function throwableProvider()
    {
        return [
            'phalcon db exception' => [new DbException('db failed')],
            'phalcon model exception' => [new ModelException('model failed')],
            'pdo exception' => [new \PDOException('pdo failed')],
        ];
    }

    /**
     * @dataProvider throwableProvider
     */
    public function testMigrationErrorBecomesMigrationDriverException(\Throwable $thrown)
    {
        $migration = new class ($thrown) {
            private $thrown;

            public function __construct($thrown)
            {
                $this->thrown = $thrown;
            }

            public function setConnection($connection)
            {
                throw $this->thrown;
            }
        };
        $di = new FactoryDefault();
        $di->set('metaMigration', $migration);

        $run = new \ReflectionMethod(Driver::class, 'run');

        $this->expectException(\Gaia\Exception\MigrationDriver::class);
        $this->expectExceptionMessage($thrown->getMessage());
        $run->invoke(new Driver($di), $this->createMock(SchemaConnection::class));
    }
}
