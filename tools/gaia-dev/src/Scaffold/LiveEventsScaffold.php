<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * Plans the files a resource's live events touch: the component, the EventNames constants, the optional
 * ProjectIdResolver parent key, the controller's `$uses` and the test cases. Writing is ResourceScaffold::write,
 * which is all-or-nothing.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LiveEventsScaffold
{
    public const NAMES_PATH = 'app/api/v1/controllers/components/events/support/EventNames.php';

    public const RESOLVER_PATH = 'app/api/v1/controllers/components/events/support/ProjectIdResolver.php';

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
     * @param LiveEventsSpec $spec
     * @return string[] repository relative path => new file text
     * @throws ScaffoldException
     */
    public function plan(LiveEventsSpec $spec): array
    {
        if (is_file($this->root . '/' . $spec->componentPath())) {
            throw new ScaffoldException("{$spec->componentPath()} already exists; {$spec->model} looks scaffolded already");
        }
        $changes = [
            $spec->componentPath() => (new LiveEventsRenderer($this->root))->component($spec),
            self::NAMES_PATH => $this->names($spec),
            $spec->controllerPath() => $this->controller($spec),
            LiveEventsSpec::TEST_PATH => $this->tests($spec),
        ];
        if ($spec->parent !== null) {
            $resolver = $this->resolver($spec);
            if ($resolver !== null) {
                $changes[self::RESOLVER_PATH] = $resolver;
            }
        }

        return $changes;
    }

    /**
     * @param LiveEventsSpec $spec
     * @return string[] the event names Hermes' contract (hermes/src/contract/names.js) must gain
     */
    public function hermesNames(LiveEventsSpec $spec): array
    {
        return array_map([$spec, 'eventName'], $spec->events);
    }

    /**
     * @param LiveEventsSpec $spec
     * @return string EventNames with the constants and their ALL entries added
     * @throws ScaffoldException
     */
    private function names(LiveEventsSpec $spec): string
    {
        $text = $this->read(self::NAMES_PATH);
        $constants = '';
        $all = '';
        foreach ($spec->events as $event) {
            $constant = $spec->constant($event);
            if (strpos($text, "const {$constant} ") !== false) {
                throw new ScaffoldException("EventNames already has {$constant}");
            }
            $constants .= "    /**\n     * {$spec->model} {$event}.\n     *\n     * @var string\n     */\n"
                . "    const {$constant} = '{$spec->eventName($event)}';\n\n";
            $all .= "        self::{$constant},\n";
        }
        $marker = "    /**\n     * Every name HermesPublisher is allowed to post.";
        if (strpos($text, $marker) === false || !preg_match('/(const ALL = array\(\n.*?\n)(    \);)/s', $text, $match, PREG_OFFSET_CAPTURE)) {
            throw new ScaffoldException(self::NAMES_PATH . ' no longer has the ALL list the scaffold extends');
        }
        $text = substr_replace($text, $all, $match[2][1], 0);

        return str_replace($marker, $constants . $marker, $text);
    }

    /**
     * @param LiveEventsSpec $spec
     * @return string|null the resolver with the parent key added, null when it already has it
     * @throws ScaffoldException
     */
    private function resolver(LiveEventsSpec $spec): ?string
    {
        [$key, $parent] = $spec->parent;
        $text = $this->read(self::RESOLVER_PATH);
        if (!preg_match('/(\$parentKeys = array\(\n)(.*?)(\n    \);)/s', $text, $match, PREG_OFFSET_CAPTURE)) {
            throw new ScaffoldException(self::RESOLVER_PATH . ' has no $parentKeys array');
        }
        if (preg_match("/(?:^|\n)\s*'{$key}'\s*=>\s*'([^']+)'\s*,/", $match[2][0], $existing)) {
            if ($existing[1] !== $parent) {
                throw new ScaffoldException("ProjectIdResolver already maps '{$key}' to '{$existing[1]}', not '{$parent}'");
            }
            return null;
        }

        return substr_replace($text, $match[2][0] . "\n        '{$key}' => '{$parent}',", $match[2][1], strlen($match[2][0]));
    }

    /**
     * @param LiveEventsSpec $spec
     * @return string the controller with the component added to `$uses`
     * @throws ScaffoldException
     */
    private function controller(LiveEventsSpec $spec): string
    {
        $path = $spec->controllerPath();
        if (!is_file($this->root . '/' . $path)) {
            throw new ScaffoldException("{$spec->model} has no controller: {$path}");
        }
        $text = $this->read($path);
        $entry = "'" . str_replace('\\', '\\\\', $spec->usesName()) . "'";
        if (strpos($text, $entry) !== false) {
            throw new ScaffoldException("{$path} already uses {$spec->usesName()}");
        }
        if (preg_match('/(\$uses = )(array\(|\[)([^\]\)]*)(\)|\])/', $text, $match, PREG_OFFSET_CAPTURE)) {
            $list = $match[3][0];
            $list = trim($list, " \t\r\n,") === '' ? $entry : rtrim($list, " \t\r\n,") . ', ' . $entry;
            return substr_replace($text, $list, $match[3][1], strlen($match[3][0]));
        }
        $declaration = "    /**\n     * Components that this controller uses.\n     *\n     * @var  \$uses\n     * @type array\n     */\n"
            . "    public \$uses = array({$entry});\n";
        if (!preg_match('/class \w+ extends \w+\n\{\n/', $text, $class, PREG_OFFSET_CAPTURE)) {
            throw new ScaffoldException("{$path} has no class body to add \$uses to");
        }

        return substr_replace($text, $declaration, $class[0][1] + strlen($class[0][0]), 0);
    }

    /**
     * @param LiveEventsSpec $spec
     * @return string the test class with the import and one case per event added
     * @throws ScaffoldException
     */
    private function tests(LiveEventsSpec $spec): string
    {
        $text = $this->read(LiveEventsSpec::TEST_PATH);
        $import = "use Gaia\\MVC\\REST\\Controllers\\Components\\Events\\{$spec->componentClass()};\n";
        if (strpos($text, $import) !== false) {
            throw new ScaffoldException(LiveEventsSpec::TEST_PATH . " already imports {$spec->componentClass()}");
        }
        $anchor = 'use Gaia\\MVC\\REST\\Controllers\\Components\\Events\\Support\\EventNames;';
        $method = "    public function testPublisherRejectsUnknownEventNames()";
        if (strpos($text, $anchor) === false || strpos($text, $method) === false) {
            throw new ScaffoldException(LiveEventsSpec::TEST_PATH . ' no longer has the import and case anchors');
        }
        $text = str_replace($anchor, $import . $anchor, $text);

        return str_replace($method, (new LiveEventsRenderer($this->root))->testCases($spec) . $method, $text);
    }

    /**
     * @param string $relative
     * @return string the file with LF line endings
     * @throws ScaffoldException
     */
    private function read(string $relative): string
    {
        $file = $this->root . '/' . $relative;
        if (!is_file($file)) {
            throw new ScaffoldException("Source file not found: {$relative}");
        }

        return str_replace("\r\n", "\n", file_get_contents($file));
    }
}
