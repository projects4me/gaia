<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Check\LiveEventsCheck;
use GaiaDev\Scaffold\LiveEventsRenderer;
use GaiaDev\Scaffold\LiveEventsScaffold;
use GaiaDev\Scaffold\LiveEventsSpec;
use GaiaDev\Scaffold\ScaffoldException;
use PHPUnit\Framework\TestCase;

/**
 * Tests `gaia-dev scaffold live-events` and `check live-events` (agaia-4136): the component keeps the golden
 * component's header and shape, every touchpoint is planned, and the check reports what is missing.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScaffoldLiveEventsTest extends TestCase
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
     * Golden test: an idea-shaped spec renders the reference component's header, constants and hooks.
     */
    public function testTheComponentMatchesTheGoldenIdeaComponent()
    {
        $spec = new LiveEventsSpec('Idea', ['events' => 'created,updated', 'fields' => 'title,status']);
        $component = (new LiveEventsRenderer(self::ROOT))->component($spec);
        $golden = str_replace("\r\n", "\n", file_get_contents(self::ROOT . '/' . LiveEventsRenderer::GOLDEN));

        $this->assertSame(substr($golden, 0, strpos($golden, '/**' . "\n" . ' * This component')), substr($component, 0, strpos($component, '/**' . "\n" . ' * This component')));
        $this->assertStringContainsString("const CREATED_FIELDS = array(\n        'title',\n        'status',\n    );", $component);
        $this->assertStringContainsString('$this->publishIdeaEvent(EventNames::IDEA_CREATED, $model, $this->pick(self::CREATED_FIELDS, $model));', $component);
        $this->assertStringContainsString('$changes = $this->changesFromAudit($model, self::UPDATED_FIELDS);', $component);
        $this->assertStringContainsString("\$this->publish(\$eventName, \$model, 'idea', \$id, \$changes, array('ideaId' => \$id));", $component);
        $this->assertNotEmpty(token_get_all($component, TOKEN_PARSE));
    }

    public function testThePlanTouchesEveryPointAndCompiles()
    {
        $root = $this->copyOfTheEventFiles();
        $spec = new LiveEventsSpec('Report', ['events' => 'created,deleted', 'prefix' => 'report', 'fields' => 'title', 'parent' => 'reportId:Report']);
        $scaffold = new LiveEventsScaffold($root);
        $changes = $scaffold->plan($spec);

        $this->assertSame([
            'app/api/v1/controllers/components/events/ReportLiveEventsComponent.php',
            'app/api/v1/controllers/components/events/support/EventNames.php',
            'app/api/v1/controllers/ReportController.php',
            'tests/api/controllers/components/events/LiveEventsComponentsTest.php',
            'app/api/v1/controllers/components/events/support/ProjectIdResolver.php',
        ], array_keys($changes));
        foreach ($changes as $text) {
            $this->assertNotEmpty(token_get_all($text, TOKEN_PARSE));
        }
        $this->assertStringContainsString("const REPORT_CREATED = 'report.created';", $changes['app/api/v1/controllers/components/events/support/EventNames.php']);
        $this->assertStringContainsString("        self::REPORT_DELETED,\n    );", $changes['app/api/v1/controllers/components/events/support/EventNames.php']);
        $this->assertStringContainsString("'reportId' => 'Report',", $changes['app/api/v1/controllers/components/events/support/ProjectIdResolver.php']);
        $this->assertStringContainsString("array('Events\\\\ReportLiveEvents')", $changes['app/api/v1/controllers/ReportController.php']);
        $this->assertStringContainsString('public function testReportCreatedPublishesItsEvent()', $changes[LiveEventsSpec::TEST_PATH]);
        $this->assertSame(['report.created', 'report.deleted'], $scaffold->hermesNames($spec));
    }

    public function testAnExistingControllerListIsExtendedNotReplaced()
    {
        $root = $this->copyOfTheEventFiles();
        file_put_contents($root . '/app/api/v1/controllers/ReportController.php', "<?php\nclass ReportController extends RestController\n{\n    public \$uses = ['Notification'];\n}\n");
        $changes = (new LiveEventsScaffold($root))->plan(new LiveEventsSpec('Report', []));

        $this->assertStringContainsString("['Notification', 'Events\\\\ReportLiveEvents']", $changes['app/api/v1/controllers/ReportController.php']);
    }

    public function testBadInputIsRejected()
    {
        foreach ([['report', []], ['Report', ['events' => 'sent']], ['Report', ['prefix' => 'Bad Prefix']], ['Report', ['parent' => 'x']]] as [$model, $options]) {
            try {
                new LiveEventsSpec($model, $options);
                $this->fail('expected a ScaffoldException');
            } catch (ScaffoldException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->expectException(ScaffoldException::class);
        (new LiveEventsScaffold(self::ROOT))->plan(new LiveEventsSpec('Idea', []));
    }

    public function testTheCheckIsGreenOnThisBranch()
    {
        $this->assertSame([], (new LiveEventsCheck(self::ROOT))->problems());
    }

    public function testTheCheckReportsAComponentNoControllerUsesAndANameWithNoTest()
    {
        $root = $this->copyOfTheEventFiles();
        $scaffold = new LiveEventsScaffold($root);
        $changes = $scaffold->plan(new LiveEventsSpec('Report', []));
        file_put_contents($root . '/app/api/v1/controllers/components/events/ReportLiveEventsComponent.php', $changes['app/api/v1/controllers/components/events/ReportLiveEventsComponent.php']);
        file_put_contents($root . '/app/api/v1/controllers/components/events/support/EventNames.php', $changes['app/api/v1/controllers/components/events/support/EventNames.php']);

        $this->assertSame([
            'EventNames::REPORT_CREATED (report.created) has no case in tests/api/controllers/components/events/LiveEventsComponentsTest.php',
            "ReportLiveEventsComponent is in no controller's \$uses",
        ], (new LiveEventsCheck($root))->problems());
    }

    /**
     * @return string a scratch repository with the live-events files and controllers of this one plus a bare Report controller
     */
    private function copyOfTheEventFiles(): string
    {
        $root = sys_get_temp_dir() . '/gaia-dev-live-' . uniqid();
        $this->scratch[] = $root;
        foreach (['app/api/v1/controllers/components/events', 'tests/api/controllers/components/events'] as $directory) {
            $this->copy(self::ROOT . '/' . $directory, $root . '/' . $directory);
        }
        foreach (glob(self::ROOT . '/app/api/v1/controllers/*Controller.php') as $file) {
            if (!is_dir($root . '/app/api/v1/controllers')) {
                mkdir($root . '/app/api/v1/controllers', 0777, true);
            }
            copy($file, $root . '/app/api/v1/controllers/' . basename($file));
        }
        file_put_contents($root . '/app/api/v1/controllers/ReportController.php', "<?php
class ReportController extends RestController
{
}
");

        return $root;
    }

    private function copy(string $from, string $to): void
    {
        if (is_file($from)) {
            if (!is_dir(dirname($to))) {
                mkdir(dirname($to), 0777, true);
            }
            copy($from, $to);
            return;
        }
        foreach (scandir($from) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->copy($from . '/' . $entry, $to . '/' . $entry);
            }
        }
    }

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
