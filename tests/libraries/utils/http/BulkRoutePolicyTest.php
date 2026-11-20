<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils\Http;

use Gaia\Core\MVC\Http\BulkRoutePolicy;
use PHPUnit\Framework\TestCase;

/**
 * Tests which calls a bulk batch may hold: only resolvable /api/v1 REST
 * routes, never bulk itself, unauthenticated resources or file streams.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BulkRoutePolicyTest extends TestCase
{
    private const ALL = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

    private function policy(): BulkRoutePolicy
    {
        return new BulkRoutePolicy(
            [
                'issue' => self::ALL,
                'upload' => self::ALL,
                'userimage' => ['POST'],
                'milestoneoverview' => ['GET'],
                'bulk' => ['POST'],
                'token' => ['POST'],
            ],
            static function (string $resource): bool {
                return $resource === 'token';
            }
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function allowedProvider(): array
    {
        return [
            'list with query' => ['GET', '/api/v1/issue?query=((Issue.status : new))&limit=5'],
            'by id' => ['GET', '/api/v1/issue/api-test-issue-000001'],
            'related' => ['GET', '/api/v1/issue/api-test-issue-000001/comments'],
            'trailing slash' => ['GET', '/api/v1/issue/'],
            'create' => ['POST', '/api/v1/issue'],
            'update' => ['PATCH', '/api/v1/issue/abc'],
            'delete' => ['DELETE', '/api/v1/issue/abc'],
            'upload list' => ['GET', '/api/v1/upload'],
            'upload delete' => ['DELETE', '/api/v1/upload/abc'],
        ];
    }

    /**
     * @dataProvider allowedProvider
     */
    public function testRunnableRestCallsAreAllowed(string $method, string $url): void
    {
        $this->assertNull($this->policy()->refusal($method, $url));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function refusedProvider(): array
    {
        return [
            'absolute url' => ['GET', 'http://evil.example/api/v1/issue', 'is not a relative'],
            'protocol relative' => ['GET', '//evil.example/api/v1/issue', 'is not a relative'],
            'other version' => ['GET', '/api/v2/issue', 'is not a relative'],
            'outside the api' => ['GET', '/download?id=1', 'is not a relative'],
            'dot segments' => ['GET', '/api/v1/issue/../token', 'is not a relative'],
            'fragment' => ['GET', '/api/v1/issue#x', 'is not a relative'],
            'bare prefix' => ['GET', '/api/v1/', 'is not a relative'],
            'unknown resource' => ['GET', '/api/v1/nosuchthing', 'is not a Gaia REST route'],
            'nested bulk' => ['POST', '/api/v1/bulk', "'bulk' cannot run in a bulk request"],
            'unauthenticated' => ['POST', '/api/v1/token', "'token' cannot run in a bulk request"],
            'method not routed' => ['POST', '/api/v1/milestoneoverview', 'is not a Gaia REST route'],
            'post with id' => ['POST', '/api/v1/issue/abc', 'is not a Gaia REST route'],
            'too deep' => ['GET', '/api/v1/issue/a/b/c', 'is not a Gaia REST route'],
            'upload bytes' => ['GET', '/api/v1/upload/abc', 'streams a file'],
            'upload file' => ['POST', '/api/v1/upload', 'streams a file'],
            'user image' => ['POST', '/api/v1/userimage', 'streams a file'],
        ];
    }

    /**
     * @dataProvider refusedProvider
     */
    public function testCallsABatchMayNotHoldAreRefused(string $method, string $url, string $reason): void
    {
        $refusal = $this->policy()->refusal($method, $url);

        $this->assertNotNull($refusal);
        $this->assertStringContainsString($reason, $refusal);
    }

    public function testControllersWithUnauthenticatedActionsAreAnonymous(): void
    {
        $this->assertTrue(BulkRoutePolicy::controllerIsAnonymous('token'));
        $this->assertTrue(BulkRoutePolicy::controllerIsAnonymous('introspect'));
        $this->assertTrue(BulkRoutePolicy::controllerIsAnonymous('serviceenrollment'));
        $this->assertTrue(BulkRoutePolicy::controllerIsAnonymous('nosuchresource'));
        $this->assertFalse(BulkRoutePolicy::controllerIsAnonymous('issue'));
    }

    public function testRefusedV1ResourcesArePinned(): void
    {
        $config = include APP_PATH . '/core/config/routes.php';
        $anonymous = array_values(array_filter(
            array_keys($config['routes']['rest']['v1']),
            [BulkRoutePolicy::class, 'controllerIsAnonymous']
        ));
        sort($anonymous);

        $this->assertSame(
            ['forgetpassword', 'introspect', 'resetpassword', 'serviceenrollment', 'token'],
            $anonymous,
            'A v1 resource changed whether a bulk batch may call it; update this list on purpose.'
        );
    }
}
