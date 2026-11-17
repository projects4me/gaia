<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Events;

/**
 * The registry of the domain events Gaia raises, read from
 * app/metadata/events.php: what each event is, its scope, the write that
 * raises it, the channels it goes out on and whether it is enabled
 * (live-updates D-013). The file is loaded once per request and shared;
 * tests pass their own definitions.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Events
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class EventRegistry
{
    public const CHANNEL_HERMES = 'hermes';
    public const CHANNEL_WORKFLOW = 'workflow';

    public const SCOPE_PROJECT = 'project';
    public const SCOPE_USER = 'user';

    /**
     * The registry file, relative to APP_PATH.
     */
    public const FILE = '/app/metadata/events.php';

    /**
     * The definitions loaded from the file, shared for the request.
     *
     * @var array|null
     */
    private static $loaded = null;

    /**
     * Event definitions by name.
     *
     * @var array
     */
    private $events;

    /**
     * @param array|null $events definitions by name; null reads the registry file
     */
    public function __construct(?array $events = null)
    {
        $this->events = $events === null ? self::load() : $events;
    }

    /**
     * Whether the registry records the event, enabled or not.
     *
     * @param  string $name
     * @return bool
     */
    public function has($name)
    {
        return is_string($name) && isset($this->events[$name]);
    }

    /**
     * The event's definition, or null when the registry does not record it.
     *
     * @param  string $name
     * @return array|null
     */
    public function definition($name)
    {
        return $this->has($name) ? $this->events[$name] : null;
    }

    /**
     * Whether the event is recorded and enabled.
     *
     * @param  string $name
     * @return bool
     */
    public function enabled($name)
    {
        return $this->has($name) && !empty($this->events[$name]['enabled']);
    }

    /**
     * Whether the event is enabled and goes out on the channel.
     *
     * @param  string $name
     * @param  string $channel one of the CHANNEL_* constants
     * @return bool
     */
    public function publishes($name, $channel)
    {
        return $this->enabled($name)
            && in_array($channel, (array) ($this->events[$name]['channels'] ?? []), true);
    }

    /**
     * The enabled events on the channel, in registry order.
     *
     * @param  string $channel one of the CHANNEL_* constants
     * @return string[]
     */
    public function names($channel)
    {
        $names = [];
        foreach (array_keys($this->events) as $name) {
            if ($this->publishes($name, $channel)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The event's scope (SCOPE_PROJECT or SCOPE_USER), or null when unknown.
     *
     * @param  string $name
     * @return string|null
     */
    public function scope($name)
    {
        return $this->has($name) ? ($this->events[$name]['scope'] ?? self::SCOPE_PROJECT) : null;
    }

    /**
     * Reads the registry file the first time it is needed in the request.
     *
     * @return array
     */
    private static function load()
    {
        if (self::$loaded === null) {
            self::$loaded = require APP_PATH . self::FILE;
        }

        return self::$loaded;
    }
}
