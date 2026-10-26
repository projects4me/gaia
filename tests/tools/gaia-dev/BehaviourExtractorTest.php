<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\BehaviourExtractor;
use GaiaDev\Extractor\MetadataModelExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the behaviour extractor (agaia-4129).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BehaviourExtractorTest extends TestCase
{
    public function testExtractsBehavioursWithPublicHooksOnly()
    {
        $records = array_column((new BehaviourExtractor(__DIR__ . '/fixtures/repo'))->extract(), null, 'name');

        $this->assertSame(['auditBehavior', 'softDeleteBehavior'], array_keys($records));
        $this->assertSame('Gaia\MVC\Models\Behaviors\softDeleteBehavior', $records['softDeleteBehavior']['class']);
        $this->assertSame('core/mvc/models/behaviors/softDeleteBehavior.php', $records['softDeleteBehavior']['file']);
        $this->assertSame(['notify'], $records['softDeleteBehavior']['hooks']);
    }

    public function testEveryBehaviourModelsDeclareHasASourceFile()
    {
        $root = dirname(__DIR__, 3);
        $known = array_column((new BehaviourExtractor($root))->extract(), 'name');

        foreach ((new MetadataModelExtractor($root))->extract() as $model) {
            foreach ($model['behaviors'] as $behaviour) {
                $this->assertContains($behaviour, $known, "{$model['name']} declares {$behaviour}");
            }
        }
    }
}
