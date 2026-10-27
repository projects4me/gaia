<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Check\ResourceCheck;
use GaiaDev\Cli;
use GaiaDev\Query\GraphReader;
use GaiaDev\Scaffold\ResourceRenderer;
use GaiaDev\Scaffold\ResourceScaffold;
use GaiaDev\Scaffold\ResourceSpec;
use GaiaDev\Scaffold\ScaffoldException;
use PHPUnit\Framework\TestCase;

/**
 * Tests `gaia-dev scaffold resource` and `check resource` (agaia-4134): the files rendered from the Idea
 * reference equal the committed ones, the registrations land where the generator and routes expect them,
 * and every model on this branch passes the check.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScaffoldResourceTest extends TestCase
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
     * @return ResourceSpec the Idea resource as it would be asked for
     */
    private function ideaSpec(): ResourceSpec
    {
        return new ResourceSpec('Idea', [
            'fields' => 'title:varchar:200:required,description:text,status:varchar:25:required:default=open',
            'parent' => 'Project',
        ]);
    }

    /**
     * Golden test: scaffolding Idea reproduces the committed model, controller and metadata. The controller's
     * live events and the metadata's child relationships are added by hand, so they are left out of the comparison.
     */
    public function testScaffoldingIdeaReproducesTheCommittedFiles()
    {
        $files = (new ResourceRenderer(self::ROOT))->render($this->ideaSpec());

        $this->assertSame($this->committed('app/models/Idea.php'), $files['app/models/Idea.php']);

        $controller = preg_replace('/\{\n    \/\*\*.*\n\}/s', "{\n}", $this->committed('app/api/v1/controllers/IdeaController.php'));
        $this->assertSame($controller, $files['app/api/v1/controllers/IdeaController.php']);

        $metadata = preg_replace('/(\'hasMany\' => array\().*?(\n        \),\n    \),)/s', '$1$2', $this->committed('app/metadata/model/Idea.php'));
        $this->assertSame($metadata, $files['app/metadata/model/Idea.php']);
    }

    public function testAResourceWithoutAParentHasNoParentFieldRelationshipOrAcl()
    {
        $files = (new ResourceRenderer(self::ROOT))->render(new ResourceSpec('Note', ['fields' => 'body:text']));
        $metadata = $files['app/metadata/model/Note.php'];

        $this->assertStringContainsString("\$models['Note'] = array(", $metadata);
        $this->assertStringContainsString("'tableName' => 'notes'", $metadata);
        $this->assertStringContainsString("'LBL_NOTES_BODY'", $metadata);
        $this->assertStringNotContainsString('projectId', $metadata);
        $this->assertStringNotContainsString('groupKeys', $metadata);
        $this->assertStringNotContainsString('Idea', $metadata);
        $this->assertStringContainsString('class NoteController extends RestController', $files['app/api/v1/controllers/NoteController.php']);
        $this->assertStringNotContainsString('$uses', $files['app/api/v1/controllers/NoteController.php']);
    }

    public function testThePlanRegistersTheResourceInEveryList()
    {
        $spec = new ResourceSpec('Report', ['fields' => 'title:varchar:100:required', 'parent' => 'Project']);
        $changes = (new ResourceScaffold(self::ROOT))->plan($spec, []);

        $this->assertStringContainsString("    'Project',\n    'Report',\n    'Role',", $changes['core/config/models.php']);
        $this->assertStringContainsString("'path' => '/api/:version/report'", $changes['core/config/routes.php']);
        $generator = $changes['tools/api-tester/bin/generate-backend-apis.php'];
        $this->assertStringContainsString("    'report' => '{reportId}',", $generator);
        $this->assertStringContainsString("        'reportId' => '{reportId}',", $generator);
        $this->assertStringContainsString("        'reportId' => true,", $generator);
        $harness = $changes['tools/api-tester/harness/prepare-test-db.sh'];
        $this->assertStringContainsString("('agentcredential'), ('report')\n) AS m(module)", $harness);
        $this->assertStringContainsString('INSERT INTO reports (', $harness);
        $this->assertStringContainsString('"reportId": "api-test-report-000001"', $harness);
        $this->assertStringContainsString('"otherProjectReportId": "api-test-report-000002"', $changes['tools/api-tester/fixtures/default.json']);
        $this->assertFileDoesNotExist(self::ROOT . '/app/models/Report.php');
    }

    public function testAnExistingResourceIsNotScaffoldedAgain()
    {
        $this->expectException(ScaffoldException::class);
        (new ResourceScaffold(self::ROOT))->plan(new ResourceSpec('Idea', []), []);
    }

    public function testBadInputIsRejectedWithAnExplanation()
    {
        foreach ([['idea', []], ['Idea', ['fields' => 'title:varchar']], ['Idea', ['fields' => 'title:blob']], ['Idea', ['acl' => 'x']], ['Report', ['parent' => 'Epic']]] as $case) {
            try {
                new ResourceSpec($case[0], $case[1]);
                $this->fail('Expected a ScaffoldException for ' . $case[0] . json_encode($case[1]));
            } catch (ScaffoldException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testUserFieldsAreNotRenamedLikeTheReference()
    {
        $spec = new ResourceSpec('Report', ['fields' => 'userIdea:varchar:50:default=Ideas rock']);
        $files = (new ResourceScaffold(self::ROOT))->plan($spec, []);

        $metadata = $files['app/metadata/model/Report.php'];
        $this->assertStringContainsString("'name' => 'userIdea'", $metadata);
        $this->assertStringContainsString('Ideas rock', $metadata);
        $this->assertStringContainsString("'LBL_REPORTS_USER_IDEA'", $metadata);
    }

    public function testTheCommandDryRunWritesNothing()
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        $code = Cli::main(['gaia-dev', 'scaffold', 'resource', 'Report', '--parent=Project', '--fields=title:varchar:100:required', '--dry-run'], self::ROOT, $out, $err);
        rewind($out);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('app/metadata/model/Report.php', stream_get_contents($out));
        $this->assertFileDoesNotExist(self::ROOT . '/app/models/Report.php');
    }

    public function testCheckResourceIsGreenForEveryModelOnThisBranch()
    {
        $graph = GraphReader::fromFile(self::ROOT . '/docs/generated/graph.json');
        $check = new ResourceCheck(self::ROOT, $graph);
        preg_match_all("/^\\s*'([A-Za-z0-9]+)',?\\s*$/m", $this->committed('core/config/models.php'), $names);

        $problems = [];
        foreach ($names[1] as $name) {
            $problems = array_merge($problems, $check->problems($name));
        }
        $this->assertSame([], $problems);
    }

    public function testCheckResourceNamesWhatIsMissing()
    {
        $problems = (new ResourceCheck(self::ROOT, GraphReader::fromFile(self::ROOT . '/docs/generated/graph.json')))->problems('Nosuchmodel');

        $this->assertCount(3, $problems);
        $this->assertStringContainsString('metadata:', $problems[0]);
    }

    /**
     * Runs the whole command against a copy of the sources: every touchpoint is written, the backend catalog,
     * graph and docs follow, the acl cases are appended and `check resource` is green; a second run refuses.
     */
    public function testScaffoldingAResourceIntoACopyOfTheSourcesIsCheckGreen()
    {
        $copy = sys_get_temp_dir() . '/gaia-dev-resource-' . bin2hex(random_bytes(4));
        $this->scratch[] = $copy;
        foreach (['app', 'core', 'db', 'tools', 'config', 'public', 'docs'] as $directory) {
            if (is_dir(self::ROOT . '/' . $directory)) {
                $this->copyTree(self::ROOT . '/' . $directory, $copy . '/' . $directory);
            }
        }
        $aclBefore = count(json_decode(file_get_contents($copy . '/tools/api-tester/apis/acl.json'), true)['apis']);

        $run = function (array $argv) use ($copy) {
            $out = fopen('php://memory', 'w+');
            $err = fopen('php://memory', 'w+');
            // ModelQuery prints 'via Array' for path-rule group keys (committed in the docs) and raises a notice; PHPUnit would turn it into an error.
            set_error_handler(function () {
                return true;
            }, E_NOTICE);
            $code = Cli::main($argv, $copy, $out, $err);
            restore_error_handler();
            rewind($out);
            rewind($err);

            return [$code, stream_get_contents($out), stream_get_contents($err)];
        };

        list($code, $out, $err) = $run(['gaia-dev', 'scaffold', 'resource', 'Report', '--parent=Project', '--fields=title:varchar:100:required,body:text', '--acl=aclMembers:members']);
        $this->assertSame(0, $code, $err . $out);
        $this->assertFileExists($copy . '/app/models/Report.php');
        $this->assertFileExists($copy . '/app/api/v1/controllers/ReportController.php');
        $this->assertStringContainsString('"/api/v1/report"', file_get_contents($copy . '/tools/api-tester/apis/backend.json'));
        $this->assertStringContainsString('"report": {', file_get_contents($copy . '/tools/api-tester/sources/server-routes/routes.v1.json'));
        $this->assertGreaterThan($aclBefore, count(json_decode(file_get_contents($copy . '/tools/api-tester/apis/acl.json'), true)['apis']));
        $this->assertStringContainsString('"report"', file_get_contents($copy . '/docs/generated/graph.json'));

        $this->assertSame([0, "resource Report: ok
", ''], $run(['gaia-dev', 'check', 'resource', 'Report']));
        $this->assertSame(0, $run(['gaia-dev', 'check', 'api-cases'])[0]);
        $this->assertSame(1, $run(['gaia-dev', 'scaffold', 'resource', 'Report'])[0]);
    }

    /**
     * @param string $from
     * @param string $to
     */
    private function copyTree(string $from, string $to): void
    {
        mkdir($to, 0777, true);
        foreach (scandir($from) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            is_dir($from . '/' . $entry) ? $this->copyTree($from . '/' . $entry, $to . '/' . $entry) : copy($from . '/' . $entry, $to . '/' . $entry);
        }
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
