<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Cli;
use PHPUnit\Framework\TestCase;

/**
 * Tests the gaia-dev query commands (model, route, acl, cases, assertions,
 * profiles, fixtures) against the committed graph (agaia-4131).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class QueryCommandTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /**
     * @param string ...$args
     * @return array [exit code, stdout, stderr]
     */
    private function gaiaDev(string ...$args): array
    {
        $stdout = fopen('php://memory', 'w+');
        $stderr = fopen('php://memory', 'w+');
        $code = Cli::main(array_merge(['gaia-dev'], $args), self::ROOT, $stdout, $stderr);
        rewind($stdout);
        rewind($stderr);

        return [$code, stream_get_contents($stdout), stream_get_contents($stderr)];
    }

    /**
     * @return array[] command line for Idea
     */
    public function ideaCommands(): array
    {
        return [
            'model' => [['model', 'Idea']],
            'route' => [['route', 'idea']],
            'route detail' => [['route', 'POST /api/v1/idea']],
            'acl' => [['acl', 'idea']],
            'acl profile' => [['acl', 'idea', 'aclNoProject']],
            'cases' => [['cases', 'idea']],
        ];
    }

    /**
     * @dataProvider ideaCommands
     * @param string[] $args
     */
    public function testIdeaAnswersStayUnderFortyLines(array $args)
    {
        [$code, $out, $err] = $this->gaiaDev(...$args);

        $this->assertSame(0, $code, $err);
        $this->assertLessThanOrEqual(40, substr_count($out, "\n"), $out);
    }

    public function testModelShowsTableFieldsRelationshipsAndController()
    {
        [, $out] = $this->gaiaDev('model', 'idea');

        $this->assertStringContainsString('Model Idea (table ideas', $out);
        $this->assertStringContainsString('title varchar(200) required', $out);
        $this->assertStringContainsString('project hasOne -> Project', $out);
        $this->assertStringContainsString('IdeaController', $out);
        $this->assertStringContainsString('GET /api/v1/idea/{id}', $out);
        $this->assertStringContainsString('gaia-dev cases idea', $out);
    }

    public function testRouteShowsHookSequenceAndComponents()
    {
        [, $out] = $this->gaiaDev('route', 'POST /api/v1/idea');

        $this->assertStringContainsString('requires: idea.create', $out);
        $this->assertStringContainsString('fire rest:beforeCreate', $out);
        $this->assertStringContainsString('fire rest:afterCreate', $out);
        $this->assertStringContainsString('Events\IdeaLiveEvents on afterCreate', $out);
    }

    public function testRouteAcceptsAPathWithoutMethod()
    {
        [$code, $out] = $this->gaiaDev('route', '/api/v1/idea/{id}');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('GET /api/v1/idea/{id} -> idea.get', $out);
    }

    public function testAclShowsEveryProfileAndOneProfileRow()
    {
        [, $all] = $this->gaiaDev('acl', 'idea');
        [, $one] = $this->gaiaDev('acl', 'idea', 'aclNoProject');

        $this->assertStringContainsString('default: create=allow delete=allow get=allow update=allow', $all);
        $this->assertStringContainsString('anonymous: create=- delete=- get=-', $all);
        $this->assertStringContainsString('idea.get = ', $one);
        $this->assertStringContainsString('why:', $one);
    }

    public function testCasesListsCompactCasesAndFilters()
    {
        [, $out] = $this->gaiaDev('cases', 'idea', '--method=POST');
        [, $one] = $this->gaiaDev('cases', 'acl/acl-idea-denied-without-project-group');

        $this->assertStringContainsString('POST /api/v1/idea as ', $out);
        $this->assertStringNotContainsString('GET /api/v1/idea as ', $out);
        $this->assertStringContainsString('as aclNoProject [', $one);
    }

    public function testAssertionsProfilesAndFixturesListTheirVocabulary()
    {
        $this->assertStringContainsString('status -- status: expected', $this->gaiaDev('assertions')[1]);
        $this->assertStringContainsString('aclNoProject role ', $this->gaiaDev('profiles')[1]);
        $this->assertStringContainsString('projectId | ', $this->gaiaDev('fixtures')[1]);
    }

    public function testJsonOutputIsTheStructuredAnswer()
    {
        [, $out] = $this->gaiaDev('model', 'Idea', '--json');
        $answer = json_decode($out, true);

        $this->assertStringStartsWith('Model Idea', $answer['title']);
        $this->assertIsArray($answer['fields']);
    }

    public function testUnknownNameFailsWithANearestHint()
    {
        [$code, $out, $err] = $this->gaiaDev('model', 'Idae');

        $this->assertSame(1, $code);
        $this->assertSame('', $out);
        $this->assertStringContainsString("Unknown model 'Idae'. Did you mean: Idea", $err);
        $this->assertSame(1, substr_count($err, "\n"));
    }

    public function testRouteHookMapCoversTheEventsRestControllerFires()
    {
        $source = file_get_contents(self::ROOT . '/core/mvc/controllers/RestController.php');
        preg_match_all("/fire\('(rest:\w+)'/", $source, $fired);
        $mapped = file_get_contents(self::ROOT . '/tools/gaia-dev/src/Query/RouteQuery.php');

        foreach (array_unique($fired[1]) as $event) {
            $this->assertStringContainsString("'$event'", $mapped, "RouteQuery::EVENTS lacks $event");
        }
    }

    public function testMissingGraphFailsWithAHint()
    {
        [$code, , $err] = $this->gaiaDev('model', 'Idea', '--root=' . __DIR__ . '/fixtures/repo');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('graph export', $err);
    }

    public function testCasesOnlyOptionsAreRejectedByOtherQueries()
    {
        [$code, , $err] = $this->gaiaDev('acl', 'idea', '--profile=aclNoProject');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Unknown option --profile', $err);
    }

    public function testGraphWithMalformedCollectionsFailsWithAHint()
    {
        $root = sys_get_temp_dir() . '/gaia-dev-bad-graph-' . uniqid();
        mkdir($root . '/docs/generated', 0777, true);
        file_put_contents($root . '/docs/generated/graph.json', '{"nodes":"x","edges":1}');

        [$code, , $err] = $this->gaiaDev('model', 'Idea', '--root=' . $root);
        unlink($root . '/docs/generated/graph.json');
        rmdir($root . '/docs/generated');
        rmdir($root . '/docs');
        rmdir($root);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('is not a gaia-dev graph', $err);
    }
}
