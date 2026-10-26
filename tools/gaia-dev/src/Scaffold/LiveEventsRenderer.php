<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * Renders the live-events component and its test cases. The component keeps the golden reference's
 * (IdeaLiveEventsComponent) file header and imports; the test cases follow the idea cases.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LiveEventsRenderer
{
    public const GOLDEN = 'app/api/v1/controllers/components/events/IdeaLiveEventsComponent.php';

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
     * @return string the component class file
     * @throws ScaffoldException
     */
    public function component(LiveEventsSpec $spec): string
    {
        $file = $this->root . '/' . self::GOLDEN;
        if (!is_file($file)) {
            throw new ScaffoldException('Golden component not found: ' . self::GOLDEN);
        }
        $golden = str_replace("\r\n", "\n", file_get_contents($file));
        if (!preg_match('/\A(<\?php.*?\nuse Phalcon\\\\Events\\\\Event;\n\n)\/\*\*\n \* This component.*?\*\/\nclass IdeaLiveEventsComponent/s', $golden, $head)) {
            throw new ScaffoldException('The golden component ' . self::GOLDEN . ' no longer has the expected header and class');
        }

        $name = $spec->model;
        $lower = strtolower($name);
        $out = $head[1];
        $out .= "/**\n * This component publishes {$lower} " . implode(', ', $spec->events) . " events.\n *\n"
            . " * @class   {$spec->componentClass()}\n * @package Gaia\\MVC\\REST\\Controllers\\Components\\Events\n */\n"
            . "class {$spec->componentClass()} extends LiveEventsComponent\n{\n";
        $out .= $this->fieldsConstant('CREATED_FIELDS', "{$name} fields included on {$spec->eventName('created')}.", $spec, in_array('created', $spec->events, true));
        $out .= $this->fieldsConstant('UPDATED_FIELDS', "{$name} fields whose change publishes {$spec->eventName('updated')}.", $spec, in_array('updated', $spec->events, true));

        $methods = [];
        foreach ($spec->events as $event) {
            $methods[] = $this->hook($spec, $event);
        }
        $methods[] = $this->publishMethod($spec);

        return $out . implode("\n", $methods) . "}\n";
    }

    /**
     * @param LiveEventsSpec $spec
     * @return string the test methods to insert in LiveEventsComponentsTest, one per event
     */
    public function testCases(LiveEventsSpec $spec): string
    {
        $name = $spec->model;
        $lower = strtolower($name);
        $class = $spec->componentClass();
        $fields = $spec->fields;
        $out = '';
        foreach ($spec->events as $event) {
            $hook = LiveEventsSpec::HOOKS[$event];
            $method = 'test' . $name . ucfirst($event) . 'PublishesItsEvent';
            $model = "'id' => '{$lower}-1',\n            'projectId' => 'project-9'";
            $expected = 'array()';
            if ($event === 'created' && $fields) {
                $model .= implode('', array_map(function ($f) {
                    return ",
            '{$f}' => 'x'";
                }, $fields));
                $expected = 'array(' . implode(', ', array_map(function ($f) {
                    return "'{$f}' => 'x'";
                }, $fields)) . ')';
            } elseif ($event === 'updated') {
                $first = $fields[0] ?? null;
                $model .= ",\n            'audit' => array(" . ($first ? "'{$first}' => array('old' => 'a', 'new' => 'b')" : '') . ')';
                $expected = $first ? "array('{$first}' => 'b')" : 'array()';
            }
            $out .= "    public function {$method}()\n    {\n        \$posted = array();\n"
                . "        \$this->fire(\$this->make({$class}::class, \$posted), '{$hook}', (object) array(\n            {$model},\n        ));\n\n"
                . "        // TODO: assert the real payload; this is the skeleton's guess.\n"
                . "        \$this->assertCount(" . ($event === 'updated' && !$fields ? '0' : '1') . ", \$posted);\n";
            if (!($event === 'updated' && !$fields)) {
                $out .= "        \$this->assertEquals('{$spec->eventName($event)}', \$posted[0]['eventName']);\n"
                    . "        \$this->assertEquals('project-9', \$posted[0]['projectId']);\n"
                    . "        \$this->assertEquals('{$lower}', \$posted[0]['resource']['type']);\n"
                    . "        \$this->assertEquals('{$lower}-1', \$posted[0]['resource']['id']);\n"
                    . "        \$this->assertEquals({$expected}, \$posted[0]['changes']);\n";
            }
            $out .= "    }\n\n";
        }

        return $out;
    }

    /**
     * @param string $constant
     * @param string $doc
     * @param LiveEventsSpec $spec
     * @param bool $used whether an event reads the constant
     * @return string
     */
    private function fieldsConstant(string $constant, string $doc, LiveEventsSpec $spec, bool $used): string
    {
        if (!$used) {
            return '';
        }
        $items = $spec->fields ? $this->lines($spec->fields, "        '%s',") : '';
        return "    /**\n     * {$doc}\n     *\n     * @var  array\n     * @type array\n     */\n"
            . "    const {$constant} = array({$items}\n    );\n\n";
    }

    /**
     * @param LiveEventsSpec $spec
     * @param string $event
     * @return string
     */
    private function hook(LiveEventsSpec $spec, string $event): string
    {
        $hook = LiveEventsSpec::HOOKS[$event];
        $const = 'EventNames::' . $spec->constant($event);
        $model = strtolower($spec->model);
        $changes = $event === 'created' ? '$this->pick(self::CREATED_FIELDS, $model)' : 'array()';
        $body = "        \$this->publish{$spec->model}Event({$const}, \$model, {$changes});\n";
        if ($event === 'updated') {
            $body = "        \$changes = \$this->changesFromAudit(\$model, self::UPDATED_FIELDS);\n"
                . "        if (empty(\$changes)) {\n            return;\n        }\n\n"
                . "        \$this->publish{$spec->model}Event({$const}, \$model, \$changes);\n";
        }
        return "    /**\n     * Publishes {$spec->eventName($event)}.\n     *\n"
            . "     * @param  Event \$event      The event object.\n"
            . "     * @param  mixed \$controller The controller that fired the event.\n"
            . "     * @param  mixed \$model      The {$model}.\n"
            . "     * @method {$hook}\n     * @return void\n     */\n"
            . "    public function {$hook}(Event \$event, \$controller, \$model)\n    {\n{$body}    }\n";
    }

    /**
     * @param LiveEventsSpec $spec
     * @return string
     */
    private function publishMethod(LiveEventsSpec $spec): string
    {
        $name = $spec->model;
        $lower = strtolower($name);
        return "    /**\n     * Publishes a {$lower} event with the {$lower} id in meta.\n     *\n"
            . "     * @param  string \$eventName Allowlisted {$lower} event name.\n"
            . "     * @param  mixed  \$model     The {$lower} model.\n"
            . "     * @param  array  \$changes   Field values the client can apply.\n"
            . "     * @method publish{$name}Event\n     * @return void\n     */\n"
            . "    protected function publish{$name}Event(\$eventName, \$model, array \$changes)\n    {\n"
            . "        \$id = \$this->modelId(\$model);\n        if (\$id === null) {\n            return;\n        }\n\n"
            . "        \$this->publish(\$eventName, \$model, '{$lower}', \$id, \$changes, array('{$lower}Id' => \$id));\n    }\n";
    }

    /**
     * @param string[] $items
     * @param string $format sprintf format holding one %s
     * @return string each item on its own line, led by a newline
     */
    private function lines(array $items, string $format): string
    {
        $out = '';
        foreach ($items as $item) {
            $out .= "\n" . sprintf($format, $item);
        }
        return $out;
    }
}
