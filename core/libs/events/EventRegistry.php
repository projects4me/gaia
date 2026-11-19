<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Events;

/**
 * The registry of the domain events Gaia raises, read from
 * app/metadata/events.php: what each event is, its scopes, the write that
 * raises it, the channels it goes out on, whether it is enabled and whether
 * it is only planned (live-updates D-013). The file is loaded once per
 * request and shared; tests pass their own definitions. Definitions are
 * validated when the registry is built.
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
    public const SCOPE_RUNTIMES = 'runtimes';
    public const SCOPE_SERVICE = 'service';

    /**
     * The scopes an event may go to.
     */
    public const SCOPES = [self::SCOPE_PROJECT, self::SCOPE_USER, self::SCOPE_RUNTIMES, self::SCOPE_SERVICE];

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
     * @param  array|null $events definitions by name; null reads the registry file
     * @throws \UnexpectedValueException when a definition has an unknown scope or a misplaced serviceKind
     */
    public function __construct(?array $events = null)
    {
        $this->events = $events === null ? self::load() : $events;
        foreach ($this->events as $name => $definition) {
            self::validate($name, (array) $definition);
        }
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
     * Whether the event is only planned: Hermes may already allow it, but
     * Gaia does not raise it yet, so it is on no channel.
     *
     * @param  string $name
     * @return bool
     */
    public function planned($name)
    {
        return $this->has($name) && !empty($this->events[$name]['planned']);
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
     * Whether the event is enabled, not planned and goes out on the channel.
     *
     * @param  string $name
     * @param  string $channel one of the CHANNEL_* constants
     * @return bool
     */
    public function publishes($name, $channel)
    {
        return $this->enabled($name)
            && !$this->planned($name)
            && in_array($channel, (array) ($this->events[$name]['channels'] ?? []), true);
    }

    /**
     * The enabled, non-planned events on the channel, in registry order.
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
     * The scopes the event goes to (SCOPE_* constants), empty when unknown.
     *
     * @param  string $name
     * @return string[]
     */
    public function scopes($name)
    {
        return $this->has($name) ? array_values((array) ($this->events[$name]['scopes'] ?? [])) : [];
    }

    /**
     * The kind of service a service-scoped event goes to (e.g. ares), or
     * null when the event has no service scope or is unknown.
     *
     * @param  string $name
     * @return string|null
     */
    public function serviceKind($name)
    {
        return $this->has($name) ? ($this->events[$name]['serviceKind'] ?? null) : null;
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

    /**
     * Checks one definition: at least one scope, every scope known, and a
     * serviceKind exactly when the service scope is listed.
     *
     * @param  string $name
     * @param  array  $definition
     * @throws \UnexpectedValueException
     */
    private static function validate($name, array $definition)
    {
        $scopes = $definition['scopes'] ?? null;
        if (!is_array($scopes) || $scopes === []) {
            throw new \UnexpectedValueException("Event {$name} lists no scopes");
        }
        foreach ($scopes as $scope) {
            if (!in_array($scope, self::SCOPES, true)) {
                $scope = is_scalar($scope) ? $scope : gettype($scope);
                throw new \UnexpectedValueException("Event {$name} has the unknown scope '{$scope}'");
            }
        }
        $service = in_array(self::SCOPE_SERVICE, $scopes, true);
        $kind = $definition['serviceKind'] ?? null;
        if ($service && (!is_string($kind) || $kind === '')) {
            throw new \UnexpectedValueException("Event {$name} has the service scope but no serviceKind");
        }
        if (!$service && $kind !== null) {
            throw new \UnexpectedValueException("Event {$name} has a serviceKind but no service scope");
        }
    }
}
