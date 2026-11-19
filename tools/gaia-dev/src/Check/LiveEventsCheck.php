<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Check;

use GaiaDev\Scaffold\LiveEventsScaffold;
use GaiaDev\Scaffold\LiveEventsSpec;

/**
 * Checks the live events: every EventNames constant is enabled on the hermes channel in the event registry,
 * emitted by a component and covered by a case in LiveEventsComponentsTest; every non-planned registry entry on the
 * hermes channel has a constant; a planned entry is emitted by no component; every channel and scope is known and
 * serviceKind comes with the service scope only; every component is listed in a controller's `$uses`.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Check
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LiveEventsCheck
{
    /**
     * The event registry (live-updates D-013), relative to the repository root.
     */
    public const REGISTRY_PATH = 'app/metadata/events.php';

    public const CHANNELS = ['hermes', 'workflow'];

    public const SCOPES = ['project', 'user', 'runtimes', 'service'];

    /**
     * @var string
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
     * @return string[] the problems, empty when the live events are consistent
     */
    public function problems(): array
    {
        $names = $this->read(LiveEventsScaffold::NAMES_PATH);
        $tests = $this->read(LiveEventsSpec::TEST_PATH);
        $components = [];
        foreach (glob($this->root . '/' . LiveEventsSpec::EVENTS_DIRECTORY . '/*.php') ?: [] as $file) {
            $components[basename($file, '.php')] = file_get_contents($file);
        }
        $registry = $this->registry();
        $problems = [];

        preg_match_all("/const ([A-Z_]+) = '([a-z.]+)';/", $names, $constants, PREG_SET_ORDER);
        foreach ($constants as [, $constant, $value]) {
            $entry = $registry[$value] ?? null;
            $emitted = false;
            foreach ($components as $source) {
                $emitted = $emitted || preg_match('/EventNames::' . $constant . '\b/', $this->code($source)) === 1;
            }
            if (is_array($entry) && !empty($entry['planned'])) {
                if ($emitted) {
                    $problems[] = "remove planned from {$value} in " . self::REGISTRY_PATH;
                }
                continue;
            }
            if (!is_array($entry) || empty($entry['enabled']) || !in_array('hermes', (array) ($entry['channels'] ?? []), true)) {
                $problems[] = "EventNames::{$constant} ({$value}) is not enabled on the hermes channel in " . self::REGISTRY_PATH;
            }
            if (!$emitted) {
                $problems[] = "EventNames::{$constant} ({$value}) is emitted by no component";
            }
            if (strpos($tests, "'{$value}'") === false && strpos($tests, "EventNames::{$constant}") === false) {
                $problems[] = "EventNames::{$constant} ({$value}) has no case in " . LiveEventsSpec::TEST_PATH;
            }
        }

        $values = array_column($constants, 2);
        foreach ($registry as $name => $entry) {
            $entry = is_array($entry) ? $entry : [];
            $channels = (array) ($entry['channels'] ?? []);
            foreach (array_diff($channels, self::CHANNELS) as $channel) {
                $problems[] = "{$name} has the unknown channel '{$channel}' in " . self::REGISTRY_PATH;
            }
            $scopes = is_array($entry['scopes'] ?? null) ? $entry['scopes'] : [];
            if ($scopes === []) {
                $problems[] = "{$name} lists no scopes in " . self::REGISTRY_PATH;
            }
            foreach ($scopes as $scope) {
                if (!in_array($scope, self::SCOPES, true)) {
                    $problems[] = "{$name} has the unknown scope '" . (is_scalar($scope) ? $scope : '') . "' in " . self::REGISTRY_PATH;
                }
            }
            $service = in_array('service', $scopes, true);
            if ($service && (!is_string($entry['serviceKind'] ?? null) || $entry['serviceKind'] === '')) {
                $problems[] = "{$name} has the service scope but no serviceKind in " . self::REGISTRY_PATH;
            }
            if (!$service && isset($entry['serviceKind'])) {
                $problems[] = "{$name} has a serviceKind but no service scope in " . self::REGISTRY_PATH;
            }
            if (in_array('hermes', $channels, true) && empty($entry['planned']) && !in_array($name, $values, true)) {
                $problems[] = "{$name} is on the hermes channel in " . self::REGISTRY_PATH . ' but has no EventNames constant';
            }
        }

        $controllers = '';
        foreach (glob($this->root . '/app/api/v1/controllers/*.php') ?: [] as $file) {
            $controllers .= file_get_contents($file);
        }
        foreach (array_keys($components) as $class) {
            if (!preg_match('/^(\w+)LiveEventsComponent$/', $class, $match)) {
                continue;
            }
            if (strpos($controllers, "'Events\\\\{$match[1]}LiveEvents'") === false) {
                $problems[] = "{$class} is in no controller's \$uses";
            }
        }

        return $problems;
    }

    /**
     * @param string $source
     * @return string the PHP source without comments, so a commented reference does not count
     */
    private function code(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    /**
     * @return array the event registry's definitions by name, empty when the file is missing
     */
    private function registry(): array
    {
        $file = $this->root . '/' . self::REGISTRY_PATH;
        $events = is_file($file) ? require $file : [];

        return is_array($events) ? $events : [];
    }

    /**
     * @param string $relative
     * @return string the file's text, empty when missing
     */
    private function read(string $relative): string
    {
        $file = $this->root . '/' . $relative;

        return is_file($file) ? file_get_contents($file) : '';
    }
}
