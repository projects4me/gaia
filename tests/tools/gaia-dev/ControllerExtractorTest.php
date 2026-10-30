<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\ControllerExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the controller extractor (agaia-4129).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ControllerExtractorTest extends TestCase
{
    public function testExtractsControllersWithUsesAndActions()
    {
        $records = array_column((new ControllerExtractor(__DIR__ . '/fixtures/repo'))->extract(), null, 'name');

        $this->assertSame(['export', 'widget'], array_keys($records));
        $this->assertSame('Gaia\MVC\REST\Controllers\WidgetController', $records['widget']['class']);
        $this->assertSame('app/api/v1/controllers/WidgetController.php', $records['widget']['file']);
        $this->assertSame('RestController', $records['widget']['extends']);
        $this->assertSame(['Notify', 'Events\WidgetLiveEvents'], $records['widget']['uses']);
        $this->assertSame([], $records['widget']['actions']);
    }

    public function testActionsAreThePublicActionMethodsWithoutTheSuffix()
    {
        $records = array_column((new ControllerExtractor(__DIR__ . '/fixtures/repo'))->extract(), null, 'name');

        $this->assertSame(['get', 'index'], $records['export']['actions']);
        $this->assertSame([], $records['export']['uses']);
    }

    public function testReadsTheRepositoryControllers()
    {
        $records = array_column((new ControllerExtractor(dirname(__DIR__, 3)))->extract(), null, 'name');

        $this->assertSame(['Filethumb', 'Issueactivities', 'Notification', 'Events\IssueLiveEvents', 'Events\WorkflowTriggers'], $records['issue']['uses']);
        $this->assertContains('index', $records['index']['actions']);
    }
}
