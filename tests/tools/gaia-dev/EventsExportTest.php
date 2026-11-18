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

    public function testItListsEveryRegistryEventSortedByNameWithItsFields()
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
            $entry = $registry[$event['name']];
            $fields = isset($entry['serviceKind'])
                ? ['name', 'scopes', 'serviceKind', 'channels', 'enabled', 'planned', 'description']
                : ['name', 'scopes', 'channels', 'enabled', 'planned', 'description'];
            $this->assertSame($fields, array_keys($event), $event['name']);
            $this->assertSame($entry['scopes'], $event['scopes']);
            $this->assertSame($entry['channels'], $event['channels']);
            $this->assertSame($entry['enabled'], $event['enabled']);
            $this->assertSame(!empty($entry['planned']), $event['planned']);
        }
    }

    public function testItListsThePlannedEventsAndTheRuntimesScope()
    {
        $events = [];
        foreach (json_decode((new EventsExport(self::ROOT))->build()['events.json'], true) as $event) {
            $events[$event['name']] = $event;
        }

        foreach (['doc.created', 'doc.updated', 'doc.deleted'] as $name) {
            $this->assertTrue($events[$name]['planned'], $name);
            $this->assertSame(['project'], $events[$name]['scopes'], $name);
            $this->assertSame(['hermes'], $events[$name]['channels'], $name);
        }
        $this->assertTrue($events['search.semantic.changed']['planned']);
        $this->assertSame(['service'], $events['search.semantic.changed']['scopes']);
        $this->assertSame('ares', $events['search.semantic.changed']['serviceKind']);
        $this->assertSame(['user', 'runtimes'], $events['workflow.agent.run.requested']['scopes']);
        $this->assertFalse($events['workflow.agent.run.requested']['planned']);
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
