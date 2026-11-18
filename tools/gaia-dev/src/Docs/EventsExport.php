<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Docs;

use GaiaDev\Scaffold\LiveEventsScaffold;

/**
 * The event registry (app/metadata/events.php) as docs/generated/events.json: a list sorted by name of
 * {name, scopes, serviceKind (service scope only), channels, enabled, planned, description}, so Hermes can
 * check its allowlist against it in CI.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Docs
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class EventsExport
{
    public const PATH = 'events.json';

    /**
     * @var string repository root
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @return string[] path relative to docs/generated => content (none without a registry)
     */
    public function build(): array
    {
        $file = $this->root . '/' . LiveEventsScaffold::REGISTRY_PATH;
        if (!is_file($file)) {
            return [];
        }
        $registry = require $file;
        $events = [];
        $registry = is_array($registry) ? $registry : [];
        foreach ($registry as $name => $entry) {
            $event = [
                'name' => (string) $name,
                'scopes' => array_values((array) ($entry['scopes'] ?? [])),
            ];
            if (isset($entry['serviceKind'])) {
                $event['serviceKind'] = (string) $entry['serviceKind'];
            }
            $events[] = $event + [
                'channels' => array_values((array) ($entry['channels'] ?? [])),
                'enabled' => !empty($entry['enabled']),
                'planned' => !empty($entry['planned']),
                'description' => (string) ($entry['description'] ?? ''),
            ];
        }
        usort($events, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return [self::PATH => json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"];
    }
}
