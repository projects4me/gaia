<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Cli;
use GaiaDev\Extractor\ProfileExtractor;
use GaiaDev\Query\GraphReader;
use GaiaDev\Scaffold\CaseBundle;
use GaiaDev\Scaffold\CaseRequest;
use GaiaDev\Scaffold\ProfileScaffold;
use GaiaDev\Scaffold\ScaffoldException;
use PHPUnit\Framework\TestCase;

/**
 * Tests `gaia-dev scaffold api-cases`: the case bundle built from the graph, the new-profile
 * definition and the command line (agaia-4133).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ScaffoldApiCasesTest extends TestCase
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

    private function bundle(): CaseBundle
    {
        return new CaseBundle(GraphReader::fromFile(self::ROOT . '/docs/generated/graph.json'));
    }

    /**
     * @param string $catalog
     * @param string $id
     * @return array the committed case
     */
    private function committed(string $catalog, string $id): array
    {
        $apis = json_decode(file_get_contents(self::ROOT . "/tools/api-tester/apis/{$catalog}.json"), true)['apis'];
        foreach ($apis as $case) {
            if ($case['id'] === $id) {
                return $case;
            }
        }
        $this->fail("No committed case {$id}");
    }

    /**
     * Golden test: the exemplar endpoint of bundle A (GET issue as a profile denied issue.get)
     * is scaffolded as its committed case, key order included; only the id is hand-written.
     */
    public function testScaffoldingTheExemplarDenialReproducesTheCommittedCase()
    {
        $cases = $this->bundle()->build(new CaseRequest('GET', '/api/v1/issue', 'aclProjectOnly', 'acl'));

        $this->assertCount(1, $cases);
        $expected = $this->committed('acl', 'acl-issue-denied-when-issue-get-zero');
        unset($cases[0]['id'], $expected['id']);
        $this->assertSame($expected, $cases[0]);
    }

    public function testClientHappyPathUsesTheCatalogIdStyleAndStoresTheCreatedId()
    {
        $post = $this->bundle()->build(new CaseRequest('POST', 'comment', null, 'client'))[0];
        $get = $this->bundle()->build(new CaseRequest('GET', '/api/v1/comment/{id}', null, 'client'))[0];

        $this->assertSame('post-comment-api-v1-comment', $post['id']);
        $this->assertSame(['runtime.commentId' => 'data.id'], $post['store']);
        $this->assertSame(201, $post['expects']['status']);
        $this->assertTrue($post['auth']);
        $this->assertSame('get-comment-api-v1-comment-id', $get['id']);
        $this->assertSame('/api/v1/comment/{commentId}', $get['path'], 'the commentId fixture exists, so it is used');
    }

    public function testAssertionsAreAddedToTheExpectationsAndMustExist()
    {
        $case = $this->bundle()->build(new CaseRequest('GET', 'issue', null, 'client', CaseRequest::parseAssertions('idsIncludes=["a","b"],status=200')))[0];

        $this->assertSame(['a', 'b'], $case['expects']['idsIncludes']);
        $this->expectException(ScaffoldException::class);
        $this->expectExceptionMessage("Unknown assertion 'idsInclude'");
        $this->bundle()->build(new CaseRequest('GET', 'issue', null, 'client', ['idsInclude' => 1]));
    }

    public function testAclCasesFollowEachProfilesVerdictOnTheEndpoint()
    {
        $cases = $this->bundle()->build(new CaseRequest('GET', 'conversationroom', null, 'acl'));
        $byAuth = array_column($cases, 'expects', 'auth');

        $this->assertSame(403, $byAuth['aclNoConversation']['status'], 'conversationroom.get is denied');
        $this->assertSame(200, $byAuth['aclMembers']['status'], 'granted profiles get the allowed case');
        $this->assertArrayNotHasKey('default', $byAuth);
    }

    public function testMembersScopeGetsAnAllowedAndANonMemberCase()
    {
        $cases = $this->bundle()->build(new CaseRequest('GET', '/api/v1/project/{id}', 'aclMembers', 'acl'));

        $this->assertSame(['/api/v1/project/{projectId}', '/api/v1/project/{otherProjectId}'], array_column($cases, 'path'));
        $this->assertSame([200, 403], array_column(array_column($cases, 'expects'), 'status'));
    }

    public function testMissingNonMemberFixtureIsNotedNotInvented()
    {
        $bundle = $this->bundle();
        $bundle->build(new CaseRequest('GET', '/api/v1/issue/{id}', 'aclMembers', 'acl'));

        $this->assertSame([], $bundle->notes(), 'issue.get is a plain grant for aclMembers, so no member pair');
    }

    public function testUnknownRouteAndNonRestRoutesAreRefused()
    {
        $this->expectException(ScaffoldException::class);
        $this->expectExceptionMessage('Unknown route');

        $this->bundle()->build(new CaseRequest('GET', 'isue', null, 'client'));
    }

    public function testCliDryRunPrintsTheCasesAndWritesNothing()
    {
        $before = file_get_contents(self::ROOT . '/tools/api-tester/apis/client.json');

        list($code, $out, $err) = $this->gaiaDev('scaffold', 'api-cases', 'GET', 'idea', '--dry-run');

        $this->assertSame(0, $code, $err);
        $this->assertStringContainsString('would append 1 case(s) to tools/api-tester/apis/client.json', $out);
        $this->assertStringContainsString('get-idea-api-v1-idea', $out);
        $this->assertSame($before, file_get_contents(self::ROOT . '/tools/api-tester/apis/client.json'));
    }

    public function testAnIdAlreadyInTheCatalogIsRefused()
    {
        list($code, , $err) = $this->gaiaDev('scaffold', 'api-cases', 'GET', 'issue', '--dry-run');

        $this->assertSame(1, $code);
        $this->assertStringContainsString('case ids already exist in client.json: get-issue-api-v1-issue', $err);
    }

    public function testCliAppendsToTheCatalogAndAFileOfCases()
    {
        $root = $this->copyOfRepo();
        $cases = $root . '/cases.json';
        file_put_contents($cases, json_encode([['id' => 'extra-one', 'method' => 'GET', 'path' => '/api/v1/issue', 'expects' => ['status' => 200]]]));

        list($code, $out, $err) = $this->gaiaDev('scaffold', 'api-cases', '--cases-file=' . $cases, '--catalog=acl', '--root=' . $root);
        list($again, , $duplicate) = $this->gaiaDev('scaffold', 'api-cases', '--cases-file=' . $cases, '--catalog=acl', '--root=' . $root);

        $this->assertSame(0, $code, $err);
        $this->assertStringContainsString('extra-one', file_get_contents($root . '/tools/api-tester/apis/acl.json'));
        $this->assertSame(1, $again);
        $this->assertStringContainsString('case ids already exist in acl.json: extra-one', $duplicate);
    }

    public function testNewProfileIsDefinedInTheHarnessAndTheFixturesTogether()
    {
        $harness = file_get_contents(self::ROOT . '/' . ProfileExtractor::SOURCE);
        $fixtures = file_get_contents(self::ROOT . '/tools/api-tester/fixtures/default.json');

        list($newHarness, $newFixtures) = (new ProfileScaffold())->define('aclReports', 'ACL Reports', 'issue.get=1,project.get=2,setting.get=0', $harness, $fixtures);

        $this->assertSame('api-tester-acl-reports@example.com', json_decode($newFixtures, true)['authProfiles']['aclReports']['email']);
        $this->assertStringContainsString('"aclReports": {', $newHarness, 'the copy of the fixtures the script writes');
        $this->assertStringStartsWith(substr($fixtures, 0, 200), $newFixtures);

        $root = $this->scratchDirectory();
        mkdir($root . '/tools/api-tester/harness', 0777, true);
        mkdir($root . '/tools/api-tester/fixtures', 0777, true);
        file_put_contents($root . '/' . ProfileExtractor::SOURCE, $newHarness);
        file_put_contents($root . '/tools/api-tester/fixtures/default.json', $newFixtures);
        $profiles = array_column((new ProfileExtractor($root))->extract(), null, 'name');

        $this->assertSame('ACL Reports', $profiles['aclReports']['role']['name']);
        $this->assertSame(['issue.get' => 1, 'project.get' => 2, 'setting.get' => 0], $profiles['aclReports']['permissions']);
        $this->assertSame('api-test-user-acl-reports', $profiles['aclReports']['user']['id']);
        $this->assertArrayHasKey('aclNoProject', $profiles, 'existing profiles are untouched');
    }

    public function testNewProfileRefusesADuplicateAndBadPermissions()
    {
        $harness = file_get_contents(self::ROOT . '/' . ProfileExtractor::SOURCE);
        $fixtures = file_get_contents(self::ROOT . '/tools/api-tester/fixtures/default.json');
        $scaffold = new ProfileScaffold();

        foreach ([['aclMembers', 'issue.get=1', 'already exists'], ['aclNew', 'issue.get=3', 'must look like'], ['aclNew', '', 'required']] as $case) {
            try {
                $scaffold->define($case[0], 'Role', $case[1], $harness, $fixtures);
                $this->fail("expected {$case[2]}");
            } catch (ScaffoldException $e) {
                $this->assertStringContainsString($case[2], $e->getMessage());
            }
        }
    }

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
     * @return string a scratch repository root holding the graph and the acl catalog
     */
    private function copyOfRepo(): string
    {
        $root = $this->scratchDirectory();
        foreach (['docs/generated/graph.json', 'tools/api-tester/apis/acl.json', 'tools/api-tester/apis/client.json'] as $file) {
            if (!is_dir(dirname($root . '/' . $file))) {
                mkdir(dirname($root . '/' . $file), 0777, true);
            }
            copy(self::ROOT . '/' . $file, $root . '/' . $file);
        }

        return $root;
    }

    /**
     * @return string
     */
    private function scratchDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/gaia-dev-scaffold-' . bin2hex(random_bytes(4));
        mkdir($directory, 0777, true);
        $this->scratch[] = $directory;

        return $directory;
    }

    /**
     * @param string $path
     */
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
