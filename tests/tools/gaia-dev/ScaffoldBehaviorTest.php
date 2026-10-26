<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Check\BehaviorCheck;
use GaiaDev\Scaffold\BehaviorRenderer;
use GaiaDev\Scaffold\BehaviorScaffold;
use GaiaDev\Scaffold\BehaviorSpec;
use GaiaDev\Scaffold\ScaffoldException;
use PHPUnit\Framework\TestCase;

/**
 * Tests `gaia-dev scaffold behavior` and `check behavior` (agaia-4135): the skeleton keeps the golden
 * behaviour's dispatcher and compiles, the metadata entry lands last in the list, and the check reports a
 * missing class, a missing test and a failing test.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScaffoldBehaviorTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /**
     * @var string[] scratch directories to remove
     */
    private $scratch = [];

    protected function tearDown(): void
    {
        foreach ($this->scratch as $directory) {
            $this->remove($directory);
        }
    }

    /**
     * @param string $relative
     * @return string the committed file with LF line endings
     */
    private function committed(string $relative): string
    {
        return str_replace("\r\n", "\n", file_get_contents(self::ROOT . '/' . $relative));
    }

    /**
     * Golden test: the skeleton shares the reference behaviour's file header, imports and notify dispatcher.
     */
    public function testTheSkeletonKeepsTheGoldenHeaderAndDispatcher()
    {
        $spec = new BehaviorSpec('reportTotals', ['model' => 'Ideamessage', 'events' => 'beforeSave,afterFetch']);
        $files = (new BehaviorRenderer(self::ROOT))->render($spec);
        $class = $files['core/mvc/models/behaviors/reportTotalsBehavior.php'];
        $golden = $this->committed(BehaviorRenderer::GOLDEN);

        preg_match('/\n    \/\*\*\n     \* Returning false.*?\n    \}\n/s', $golden, $notify);
        $this->assertStringContainsString($notify[0], $class);
        $this->assertSame(substr($golden, 0, strpos($golden, 'namespace')), substr($class, 0, strpos($class, 'namespace')));
        $this->assertStringContainsString("namespace Gaia\\MVC\\Models\\Behaviors;\n\nuse Phalcon\\Messages\\Message;\nuse Phalcon\\Mvc\\ModelInterface;", $class);
        $this->assertStringContainsString('class reportTotalsBehavior extends Behavior implements BehaviorInterface', $class);
        $this->assertStringContainsString('protected function beforeSave($model)', $class);
        $this->assertStringContainsString('protected function afterFetch($model)', $class);
        $this->assertStringContainsString('protected function lookup($model)', $class);
    }

    public function testTheSkeletonCompilesAndItsTestFailsUntilTheRuleIsWritten()
    {
        $spec = new BehaviorSpec('reportTotalsBehavior', ['model' => 'Ideamessage']);
        $files = (new BehaviorRenderer(self::ROOT))->render($spec);

        foreach ($files as $text) {
            $this->assertNotEmpty(token_get_all($text, TOKEN_PARSE));
        }
        $test = $files['tests/models/behaviors/ReportTotalsBehaviorTest.php'];
        $this->assertStringContainsString('class ReportTotalsBehaviorTest extends TestCase', $test);
        $this->assertStringContainsString("setMethods(['lookup'])", $test);
        $this->assertStringContainsString('$this->fail(', $test);
        $this->assertStringContainsString('use Gaia\MVC\Models\Behaviors\reportTotalsBehavior;', $test);
    }

    public function testThePlanRegistersTheBehaviourLastInTheModelMetadata()
    {
        $spec = new BehaviorSpec('reportTotals', ['model' => 'Ideamessage']);
        $changes = (new BehaviorScaffold(self::ROOT))->plan($spec);

        $this->assertSame(
            ['core/mvc/models/behaviors/reportTotalsBehavior.php', 'tests/models/behaviors/ReportTotalsBehaviorTest.php', 'app/metadata/model/Ideamessage.php'],
            array_keys($changes)
        );
        $this->assertStringContainsString("        'ideaReferencesBehavior',\n        'reportTotalsBehavior'\n    ),", $changes['app/metadata/model/Ideamessage.php']);
        $this->assertFileDoesNotExist(self::ROOT . '/core/mvc/models/behaviors/reportTotalsBehavior.php');
    }

    public function testBadInputIsRejectedWithAnExplanation()
    {
        $cases = [
            ['Report', []],
            ['report', ['model' => 'idea']],
            ['Behavior', ['model' => 'Idea']],
            ['reportTotals', ['model' => 'Idea', 'events' => 'whenever']],
        ];
        foreach ($cases as $case) {
            try {
                new BehaviorSpec($case[0], $case[1]);
                $this->fail('Expected a ScaffoldException for ' . $case[0] . json_encode($case[1]));
            } catch (ScaffoldException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testAnExistingOrAlreadyRegisteredBehaviourIsNotScaffoldedAgain()
    {
        $this->expectException(ScaffoldException::class);
        (new BehaviorScaffold(self::ROOT))->plan(new BehaviorSpec('ideaScreens', ['model' => 'Ideamessage']));
    }

    public function testAnUnknownModelIsRejected()
    {
        $this->expectException(ScaffoldException::class);
        (new BehaviorScaffold(self::ROOT))->plan(new BehaviorSpec('reportTotals', ['model' => 'Nothing']));
    }

    public function testTheCheckIsGreenOnThisBranch()
    {
        $this->assertSame([], (new BehaviorCheck(self::ROOT))->problems(false));
    }

    public function testTheCheckRunsEveryTestFileAloneAndReportsFailures()
    {
        $ran = [];
        $check = new BehaviorCheck(self::ROOT, function (string $test) use (&$ran) {
            $ran[] = $test;
            return basename($test) === 'PlanVersionBehaviorTest.php' ? 'boom' : null;
        });

        $problems = $check->problems(true);

        $this->assertContains('tests/models/behaviors/IdeaScreensBehaviorTest.php', $ran);
        $this->assertContains('tests/libraries/utils/TranslationCacheBehaviorTest.php', $ran);
        $this->assertSame(['test: tests/models/behaviors/PlanVersionBehaviorTest.php does not pass alone: boom'], $problems);
    }

    public function testTheCheckReportsAMissingClassAndAMissingTest()
    {
        $root = sys_get_temp_dir() . '/gaia-dev-behavior-' . bin2hex(random_bytes(4));
        $this->scratch[] = $root;
        foreach (['core/mvc/models/behaviors', 'app/metadata/model', 'tests'] as $directory) {
            mkdir($root . '/' . $directory, 0777, true);
        }
        file_put_contents($root . '/core/mvc/models/behaviors/untestedBehavior.php', "<?php\n\nnamespace Gaia\\MVC\\Models\\Behaviors;\n\nclass untestedBehavior\n{\n}\n");
        file_put_contents($root . '/app/metadata/model/Thing.php', "<?php\n\$models['Thing'] = array(\n    'behaviors' => array(\n        'untestedBehavior',\n        'ghostBehavior'\n    ),\n);\n");

        $problems = (new BehaviorCheck($root))->problems(true);

        $this->assertSame([
            'metadata: ghostBehavior (used by Thing) has no class in core/mvc/models/behaviors',
            'test: untestedBehavior has no tests/**/UntestedBehaviorTest.php',
        ], $problems);
    }

    /**
     * @param string $path
     */
    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
            return;
        }
        if (is_dir($path)) {
            foreach (scandir($path) as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->remove($path . '/' . $entry);
                }
            }
            rmdir($path);
        }
    }
}
