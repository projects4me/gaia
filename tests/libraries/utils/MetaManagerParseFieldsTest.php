<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Meta\Manager;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Manager::parseFields on a REST module that has a controller
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

        $this->assertArrayNotHasKey('attributes', $data);
        $this->assertSame(array(), $data['default']);
    }
}
