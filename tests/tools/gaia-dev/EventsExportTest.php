<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Docs\EventsExport;
use PHPUnit\Framework\TestCase;

/**
 * Tests the docs/generated/events.json export of the event registry (agaia-4641).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class EventsExportTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function testItListsEveryRegistryEventSortedByNameWithTheFiveFields()
    {
        $pages = (new EventsExport(self::ROOT))->build();
        $events = json_decode($pages['events.json'], true);
        $registry = require self::ROOT . '/app/metadata/events.php';

        $names = array_column($events, 'name');
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names);
        $this->assertSame(count($registry), count($events));
        foreach ($events as $event) {
            $this->assertSame(['name', 'scope', 'channels', 'enabled', 'description'], array_keys($event));
            $this->assertSame($registry[$event['name']]['channels'], $event['channels']);
            $this->assertSame($registry[$event['name']]['enabled'], $event['enabled']);
        }
    }

    public function testItWritesNothingWithoutARegistry()
    {
        $this->assertSame([], (new EventsExport(sys_get_temp_dir() . '/gaia-dev-no-registry'))->build());
    }

    public function testTheCommittedFileIsCurrent()
    {
        $committed = str_replace("\r\n", "\n", file_get_contents(self::ROOT . '/docs/generated/events.json'));

        $this->assertSame((new EventsExport(self::ROOT))->build()['events.json'], $committed);
    }
}
