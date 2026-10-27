<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Check\ActionCheck;
use GaiaDev\Query\GraphReader;
use GaiaDev\Scaffold\ActionRenderer;
use GaiaDev\Scaffold\ActionScaffold;
use GaiaDev\Scaffold\ActionSpec;
use GaiaDev\Scaffold\ScaffoldException;
use PHPUnit\Framework\TestCase;

/**
 * Tests `gaia-dev scaffold action` and `check action` (agaia-4137): a thin override of a standard action with
 * the shape of the exemplar IssuestatusController (#180), its acl and client cases, and no new route.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScaffoldActionTest extends TestCase
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
     * Golden test: the skeleton only delegates; authorization stays in the parent action.
     */
    public function testTheDeleteSkeletonDelegatesToTheStandardAction()
    {
        $method = (new ActionRenderer())->method(new ActionSpec('Issuestatus', 'delete', []));

        $this->assertStringNotContainsString('requestAttributes', $method);
        $this->assertStringContainsString('return parent::deleteAction();', $method);
        $this->assertStringContainsString('@gaia-dev action delete', $method);
        $this->assertStringContainsString('IssuestatusRules', $method);
        $this->assertNotEmpty(token_get_all("<?php\nclass A\n{\n" . $method . "}\n", TOKEN_PARSE));
    }

    public function testPostAndPatchDelegateWithoutHelpersOfOtherControllers()
    {
        $post = (new ActionRenderer())->method(new ActionSpec('Tag', 'post', []));
        $patch = (new ActionRenderer())->method(new ActionSpec('Tag', 'patch', []));

        $this->assertStringContainsString('return parent::postAction();', $post);
        $this->assertStringContainsString('return parent::patchAction();', $patch);
        $this->assertStringNotContainsString('requestAttributes', $post . $patch);
        $this->assertNotEmpty(token_get_all("<?php\nclass A\n{\n" . $post . $patch . "}\n", TOKEN_PARSE));
    }

    public function testTheSpecReusesTheStandardRouteAndPermission()
    {
        $spec = new ActionSpec('TagController', 'patch', ['domain' => 'TagRules']);

        $this->assertSame('PATCH', $spec->method());
        $this->assertSame('/api/v1/tag/{id}', $spec->path());
        $this->assertSame('tag.update', $spec->permission());
        $this->assertSame('patchAction', $spec->action());
        $this->assertSame('Gaia\\Libraries\\Utils\\TagRules', $spec->domainClass());
        $this->assertSame('/api/v1/tag', (new ActionSpec('Tag', 'post', []))->path());
    }

    public function testACustomActionNameIsRefused()
    {
        $this->expectException(ScaffoldException::class);
        $this->expectExceptionMessage('Gaia has no custom actions');

        new ActionSpec('Tag', 'archive', []);
    }

    public function testAnExistingOverrideIsRefused()
    {
        $this->expectException(ScaffoldException::class);
        $this->expectExceptionMessage('already overrides postAction');

        (new ActionScaffold(self::ROOT, $this->graph()))->plan(new ActionSpec('Issuestatus', 'post', []));
    }

    public function testThePlanOverridesTheActionAndAddsNoRoute()
    {
        $spec = new ActionSpec('Issuetype', 'delete', []);
        $changes = (new ActionScaffold(self::ROOT, $this->graph()))->plan($spec);
        $controller = $changes[$spec->controllerPath()];

        $this->assertStringContainsString('public function deleteAction()', $controller);
        $this->assertStringContainsString('@gaia-dev action delete', $controller);
        $this->assertNotEmpty(token_get_all($controller, TOKEN_PARSE));
        $this->assertArrayNotHasKey('core/config/routes.php', $changes);
        foreach (array_keys($changes) as $path) {
            $this->assertMatchesRegularExpression('#^(app/api/v1/controllers/|tools/api-tester/apis/)#', $path);
        }
    }

    public function testTheCheckReportsAScaffoldedActionWithoutCases()
    {
        $spec = new ActionSpec('Tag', 'patch', []);
        $root = $this->scratchRoot();
        file_put_contents(
            $root . '/' . $spec->controllerPath(),
            "<?php
class TagController
{
" . (new ActionRenderer())->method($spec) . "}
"
        );
        $problems = implode("
", (new ActionCheck($root, $this->graph()))->problems());

        $this->assertStringContainsString('TagController::patchAction: no enabled acl case for PATCH on tag', $problems);
        $this->assertStringContainsString('TagController::patchAction: no enabled client case for PATCH on tag', $problems);
        $this->assertStringNotContainsString('route', $problems);
    }

    public function testTheCheckPassesAScaffoldedActionWhoseCasesExist()
    {
        $spec = new ActionSpec('Issuetype', 'delete', []);
        $root = $this->scratchRoot();
        file_put_contents(
            $root . '/' . $spec->controllerPath(),
            "<?php
class IssuetypeController
{
" . (new ActionRenderer())->method($spec) . "}
"
        );

        $this->assertSame([], (new ActionCheck($root, $this->graph()))->problems());
    }

    public function testTheCheckIgnoresActionsWrittenByHand()
    {
        $this->assertSame([], (new ActionCheck(self::ROOT, $this->graph()))->problems());
    }

    public function testTheCheckNeedsCasesThatExerciseTheActionsRoute()
    {
        $spec = new ActionSpec('Issuetype', 'delete', []);
        $root = $this->scratchRoot();
        file_put_contents(
            $root . '/' . $spec->controllerPath(),
            "<?php
class IssuetypeController
{
" . (new ActionRenderer())->method($spec) . "}
"
        );
        $document = $this->document();
        $document['edges'] = array_values(array_filter($document['edges'], function ($edge) {
            return !($edge['type'] === 'exercises' && $edge['to'] === 'route:DELETE /api/v1/issuetype/{id}');
        }));
        $problems = implode("
", (new ActionCheck($root, new GraphReader($document)))->problems());

        $this->assertStringContainsString('no enabled acl case for DELETE on issuetype', $problems);
        $this->assertStringContainsString('no enabled client case for DELETE on issuetype', $problems);
    }

    public function testADisabledCaseIsReportedAsAConflict()
    {
        $document = $this->document();
        $document['nodes'][] = [
            'id' => 'case:client/delete-issuetype-api-v1-issuetype-id',
            'type' => 'case',
            'key' => 'client/delete-issuetype-api-v1-issuetype-id',
            'attributes' => ['catalog' => 'client', 'resource' => 'issuetype', 'method' => 'DELETE', 'enabled' => false],
        ];
        $this->expectException(ScaffoldException::class);
        $this->expectExceptionMessage('is disabled');

        (new ActionScaffold(self::ROOT, new GraphReader($document)))->plan(new ActionSpec('Issuetype', 'delete', []));
    }

    private function document(): array
    {
        return json_decode(file_get_contents(self::ROOT . '/docs/generated/graph.json'), true);
    }

    private function graph(): GraphReader
    {
        return GraphReader::fromFile(self::ROOT . '/docs/generated/graph.json');
    }

    /**
     * @return string an empty scratch root with a controllers directory
     */
    private function scratchRoot(): string
    {
        $root = sys_get_temp_dir() . '/gaia-dev-action-' . uniqid();
        mkdir($root . '/' . ActionSpec::CONTROLLERS, 0777, true);
        $this->scratch[] = $root;

        return $root;
    }

    private function remove(string $path): void
    {
        if (is_dir($path)) {
            foreach (scandir($path) as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->remove($path . '/' . $entry);
                }
            }
            rmdir($path);
        } elseif (is_file($path)) {
            unlink($path);
        }
    }
}
