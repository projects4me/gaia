<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Db\Migration\Postgres;
use Gaia\Libraries\Meta\Manager;
use Gaia\Libraries\Meta\Migration;
use Phalcon\Db\Dialect\Postgresql;
use PHPUnit\Framework\TestCase;

/**
 * Builds the issues table the way a migration on an empty Postgres database
 * does, from the real Issue metadata, and checks that issueNumber becomes a
 * valid SERIAL column (agaia-4074).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PostgresIssueTableDefinitionTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class) || !class_exists(Migration::class)) {
            $this->markTestSkipped('Phalcon migrations are not available in this PHP runtime');
        }
    }

    public function testIssueNumberIsCreatedAsSerialNotNull()
    {
        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('migrationHandler', new Postgres());
        $di->set('metaManager', new Manager($di));
        $meta = include APP_PATH . '/app/metadata/model/Issue.php';

        $prepare = new \ReflectionMethod(Migration::class, 'prepareTableDefinition');
        $prepare->setAccessible(true);
        $definition = $prepare->invoke(new Migration($di), 'Issue', $meta);

        $sql = (new Postgresql())->createTable($definition['tableName'], 'public', array(
            'columns' => $definition['columns'],
        ));

        $this->assertStringContainsString('"issueNumber" SERIAL NOT NULL', $sql);
        $this->assertStringNotContainsString('SERIAL NULL', $sql);
    }
}
