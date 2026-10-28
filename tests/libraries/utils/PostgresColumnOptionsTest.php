<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Db\Migration\Postgres;
use Phalcon\Db\Column;
use Phalcon\Db\Dialect\Postgresql;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the column options the Postgres migration handler builds
 * from model metadata. An auto-increment column becomes SERIAL, which is
 * always NOT NULL, so metadata asking for NULL must not reach the dialect
 * (agaia-4074).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PostgresColumnOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Column::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
    }

    public function testAutoIncrementColumnDeclaredNullableIsMadeNotNull()
    {
        $options = $this->prepare(array('autoIncrement' => 'true', 'null' => true));

        $this->assertTrue($options['notNull']);
    }

    public function testNullableColumnWithoutAutoIncrementStaysNullable()
    {
        $options = $this->prepare(array('null' => true));

        $this->assertFalse($options['notNull']);
    }

    public function testCreateTableForNullableAutoIncrementColumnIsValidSerial()
    {
        $options = $this->prepare(array('autoIncrement' => 'true', 'null' => true));

        $sql = (new Postgresql())->createTable('issues', 'public', array(
            'columns' => array(new Column('issueNumber', $options)),
        ));

        $this->assertStringContainsString('"issueNumber" SERIAL NOT NULL', $sql);
        $this->assertStringNotContainsString('SERIAL NULL', $sql);
    }

    /**
     * Builds column options the way Gaia\Libraries\Meta\Migration does for an int(11) field.
     *
     * @param array $schema
     * @return array
     */
    private function prepare(array $schema)
    {
        $schema = array_merge(array('name' => 'issueNumber', 'type' => 'int', 'length' => '11'), $schema);
        $fieldOptions = array('type' => Column::TYPE_INTEGER, 'size' => $schema['length'], 'notNull' => !$schema['null']);
        if (isset($schema['autoIncrement'])) {
            $fieldOptions['autoIncrement'] = $schema['autoIncrement'];
        }

        return (new Postgres())->prepareColumnOptions($schema, $fieldOptions, array(), $schema['name']);
    }
}
