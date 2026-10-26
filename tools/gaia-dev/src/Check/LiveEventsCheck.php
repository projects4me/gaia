<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Check;

use GaiaDev\Scaffold\LiveEventsScaffold;
use GaiaDev\Scaffold\LiveEventsSpec;

/**
 * Checks the live events: every EventNames constant is in its ALL list, emitted by a component and covered by a
 * case in LiveEventsComponentsTest, and every component is listed in a controller's `$uses`.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Check
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LiveEventsCheck
{
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
        $problems = [];

        preg_match_all("/const ([A-Z_]+) = '([a-z.]+)';/", $names, $constants, PREG_SET_ORDER);
        foreach ($constants as [, $constant, $value]) {
            if (strpos($names, "self::{$constant},") === false) {
                $problems[] = "EventNames::{$constant} is not in EventNames::ALL";
            }
            $emitted = false;
            foreach ($components as $source) {
                $emitted = $emitted || preg_match('/EventNames::' . $constant . '\b/', $this->code($source)) === 1;
            }
            if (!$emitted) {
                $problems[] = "EventNames::{$constant} ({$value}) is emitted by no component";
            }
            if (strpos($tests, "'{$value}'") === false && strpos($tests, "EventNames::{$constant}") === false) {
                $problems[] = "EventNames::{$constant} ({$value}) has no case in " . LiveEventsSpec::TEST_PATH;
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
     * @param string $relative
     * @return string the file's text, empty when missing
     */
    private function read(string $relative): string
    {
        $file = $this->root . '/' . $relative;

        return is_file($file) ? file_get_contents($file) : '';
    }
}
