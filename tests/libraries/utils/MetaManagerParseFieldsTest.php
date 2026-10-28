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
}
