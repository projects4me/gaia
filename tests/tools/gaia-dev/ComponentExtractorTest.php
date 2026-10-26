<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\ComponentExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the component extractor (agaia-4129).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ComponentExtractorTest extends TestCase
{
    /**
     * @return array[] records by key
     */
    private function records(string $root): array
    {
        return array_column((new ComponentExtractor($root))->extract(), null, 'key');
    }

    public function testKeysMatchWhatControllersListInUses()
    {
        $this->assertSame(
            [
                'Notify',
                'Events\WidgetLiveEvents',
                'Events\Support\Publisher',
                'Notifications\Config\NotificationConfig',
                'Notifications\Modules\WidgetNotification',
            ],
            array_keys($this->records(__DIR__ . '/fixtures/repo'))
        );
    }

    public function testKindsFollowTheFolders()
    {
        $records = $this->records(__DIR__ . '/fixtures/repo');

        $this->assertSame('component', $records['Notify']['kind']);
        $this->assertSame('event', $records['Events\WidgetLiveEvents']['kind']);
        $this->assertSame('support', $records['Events\Support\Publisher']['kind']);
        $this->assertSame('notification', $records['Notifications\Modules\WidgetNotification']['kind']);
        $this->assertSame('support', $records['Notifications\Config\NotificationConfig']['kind']);
    }

    public function testRecordsClassFileParentInterfacesAndHooks()
    {
        $records = $this->records(__DIR__ . '/fixtures/repo');

        $event = $records['Events\WidgetLiveEvents'];
        $this->assertSame('Gaia\MVC\REST\Controllers\Components\Events\WidgetLiveEventsComponent', $event['class']);
        $this->assertSame('app/api/v1/controllers/components/events/WidgetLiveEventsComponent.php', $event['file']);
        $this->assertSame('LiveEventsComponent', $event['extends']);
        $this->assertSame(['afterUpdate'], $event['hooks']);
        $this->assertSame(['NotificationModuleInterface'], $records['Notifications\Modules\WidgetNotification']['implements']);
        $this->assertSame(['afterCreate'], $records['Notify']['hooks']);
    }

    public function testEveryComponentTheRepositoryControllersUseExists()
    {
        $root = dirname(__DIR__, 3);
        $records = $this->records($root);
        $this->assertArrayHasKey('Events\IssueLiveEvents', $records);
        $this->assertArrayHasKey('Notifications\Modules\IssueNotification', $records);

        foreach ((new \GaiaDev\Extractor\ControllerExtractor($root))->extract() as $controller) {
            foreach ($controller['uses'] as $use) {
                $this->assertArrayHasKey($use, $records, "{$controller['class']} uses {$use}");
            }
        }
    }
}
