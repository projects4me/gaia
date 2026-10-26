<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * Renders a behaviour class and its PHPUnit test. The class keeps the golden reference's header, imports and
 * `notify` dispatcher; the test follows the reference test's style and stubs the external lookup through the
 * behaviour's protected `lookup` seam.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BehaviorRenderer
{
    public const GOLDEN = 'core/mvc/models/behaviors/ideaScreensBehavior.php';

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
     * @param BehaviorSpec $spec
     * @return string[] repository relative path => file text
     * @throws ScaffoldException
     */
    public function render(BehaviorSpec $spec): array
    {
        return [
            $spec->classPath() => $this->behavior($spec),
            $spec->testPath() => $this->test($spec),
        ];
    }

    /**
     * @param BehaviorSpec $spec
     * @return string
     * @throws ScaffoldException
     */
    private function behavior(BehaviorSpec $spec): string
    {
        $golden = $this->golden();
        if (!preg_match('/\A(<\?php.*?\n)\/\*\*\n \* This behavior.*?\*\/\nclass ideaScreensBehavior/s', $golden, $head)
            || !preg_match('/\n    \/\*\*\n     \* Returning false.*?\n    \}\n/s', $golden, $notify)
        ) {
            throw new ScaffoldException('The golden behaviour ' . self::GOLDEN . ' no longer has the expected header and notify method');
        }
        $imports = "use Phalcon\\Messages\\Message;\nuse Phalcon\\Mvc\\ModelInterface;\n"
            . "use Phalcon\\Mvc\\Model\\BehaviorInterface;\nuse Phalcon\\Mvc\\Model\\Behavior;\n";
        $header = preg_replace_callback('/^use .*\n(?:use .*\n)*/m', function () use ($imports) {
            return $imports;
        }, $head[1]);

        $text = $header . "/**\n * TODO: describe the rule {$spec->name} enforces on {$spec->model}.\n *\n"
            . " * @author Hammad Hassan <gollomer@gmail.com>\n * @package Foundation\n * @category Behavior\n"
            . " * @license http://www.gnu.org/licenses/agpl.html AGPLv3\n */\n"
            . "class {$spec->name} extends Behavior implements BehaviorInterface\n{" . $notify[0];
        foreach ($spec->events as $event) {
            $text .= "\n    /**\n     * @param  ModelInterface \$model\n     * @return bool\n     */\n"
                . "    protected function {$event}(\$model)\n    {\n        \$this->lookup(\$model);\n\n        return true;\n    }\n";
        }
        $text .= "\n    /**\n     * The seam to the external system (database, cache, file store). Tests replace it, so the rule\n"
            . "     * is exercised without the system behind it.\n     *\n     * @param  ModelInterface \$model\n     * @return array\n     */\n"
            . "    protected function lookup(\$model)\n    {\n        return [];\n    }\n}\n";

        return $text;
    }

    /**
     * @param BehaviorSpec $spec
     * @return string
     */
    private function test(BehaviorSpec $spec): string
    {
        $event = $spec->events[0];
        $case = 'testHandles' . ucfirst($event);

        return <<<PHP
<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\\Tests\\Models\\Behaviors;

use PHPUnit\\Framework\\TestCase;
use Gaia\\MVC\\Models\\Behaviors\\{$spec->name};
use Phalcon\\Mvc\\ModelInterface;

/**
 * Unit tests for {$spec->name}: TODO describe the rule.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class {$spec->testClass} extends TestCase
{
    /**
     * The behaviour lets a valid {$spec->model} through. Replace the failure with the rule's real cases.
     *
     * @return void
     */
    public function {$case}()
    {
        \$behavior = \$this->behaviorFinding([]);
        \$model = \$this->createMock(ModelInterface::class);

        \$this->assertTrue(\$behavior->notify('{$event}', \$model));
        \$this->fail('{$spec->name} has no rule yet: write the failing case first, then implement it.');
    }

    /**
     * A behaviour whose external lookup finds the given records.
     *
     * @param  array \$found
     * @return {$spec->name}
     */
    private function behaviorFinding(array \$found)
    {
        \$behavior = \$this->getMockBuilder({$spec->name}::class)
            ->setMethods(['lookup'])
            ->getMock();
        \$behavior->method('lookup')->willReturn(\$found);

        return \$behavior;
    }
}

PHP;
    }

    /**
     * @return string the golden behaviour with LF line endings
     * @throws ScaffoldException
     */
    private function golden(): string
    {
        $file = $this->root . '/' . self::GOLDEN;
        if (!is_file($file)) {
            throw new ScaffoldException('Golden behaviour not found: ' . self::GOLDEN);
        }

        return str_replace("\r\n", "\n", file_get_contents($file));
    }
}
