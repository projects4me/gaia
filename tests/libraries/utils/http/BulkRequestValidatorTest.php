<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils\Http;

use Gaia\Core\MVC\Http\BulkItem;
use Gaia\Core\MVC\Http\BulkRequestValidator;
use Gaia\Core\MVC\Http\BulkRoutePolicy;
use Gaia\Exception\BadRequest;
use PHPUnit\Framework\TestCase;

/**
 * Tests that a bulk batch is checked as a whole before anything runs.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BulkRequestValidatorTest extends TestCase
{
    private function validator(int $maxRequests = 3, int $maxBodyBytes = 4096): BulkRequestValidator
    {
        $routes = new BulkRoutePolicy(
            ['issue' => ['GET', 'POST', 'PATCH', 'DELETE'], 'bulk' => ['POST']],
            static function (): bool {
                return false;
            }
        );

        return new BulkRequestValidator($routes, $maxRequests, $maxBodyBytes);
    }

    private function batch(array $requests): string
    {
        return json_encode(['requests' => $requests]);
    }

    private function call(string $id, string $method = 'GET', string $url = '/api/v1/issue', $body = null): array
    {
        return ['id' => $id, 'method' => $method, 'url' => $url, 'body' => $body];
    }

    public function testValidBatchGivesItemsInRequestOrder(): void
    {
        $body = ['data' => ['type' => 'issue', 'attributes' => ['subject' => 'x']]];
        $items = $this->validator()->validate($this->batch([
            $this->call('lanes', 'GET', '/api/v1/issue?query=((Issue.status : new))'),
            ['id' => 'create', 'method' => 'POST', 'url' => '/api/v1/issue', 'body' => $body],
            ['id' => 'nobody', 'method' => 'DELETE', 'url' => '/api/v1/issue/abc'],
        ]));

        $this->assertCount(3, $items);
        $this->assertContainsOnlyInstancesOf(BulkItem::class, $items);
        $this->assertSame(['lanes', 'create', 'nobody'], array_map(static function (BulkItem $item) {
            return $item->id();
        }, $items));
        $this->assertSame('/api/v1/issue?query=((Issue.status : new))', $items[0]->url());
        $this->assertSame('POST', $items[1]->method());
        $this->assertSame($body, $items[1]->body());
        $this->assertNull($items[2]->body());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function invalidBatchProvider(): array
    {
        $call = ['id' => 'a', 'method' => 'GET', 'url' => '/api/v1/issue'];

        return [
            'not json' => ['{', 'requests must be a non-empty array'],
            'no requests' => ['{"other": []}', 'requests must be a non-empty array'],
            'empty requests' => ['{"requests": []}', 'requests must be a non-empty array'],
            'requests is an object' => ['{"requests": {"a": ' . json_encode($call) . '}}', 'requests must be a non-empty array'],
            'too many' => [json_encode(['requests' => [
                $call, ['id' => 'b'] + $call, ['id' => 'c'] + $call, ['id' => 'd'] + $call,
            ]]), 'at most 3 requests'],
            'item not an object' => ['{"requests": ["GET /api/v1/issue"]}', 'requests[0] must be an object'],
            'missing id' => [json_encode(['requests' => [['id' => ''] + $call]]), 'requests[0].id must be a non-empty string'],
            'numeric id' => [json_encode(['requests' => [['id' => 7] + $call]]), 'requests[0].id must be a non-empty string'],
            'duplicate id' => [json_encode(['requests' => [$call, $call]]), "requests[1].id 'a' is used more than once"],
            'put' => [json_encode(['requests' => [['method' => 'PUT'] + $call]]), 'requests[0].method must be one of'],
            'lower-case method' => [json_encode(['requests' => [['method' => 'get'] + $call]]), 'requests[0].method must be one of'],
            'missing url' => [json_encode(['requests' => [['id' => 'a', 'method' => 'GET']]]), 'requests[0].url must be a string'],
            'nested bulk' => [json_encode(['requests' => [['method' => 'POST', 'url' => '/api/v1/bulk'] + $call]]), "requests[0].url: 'bulk' cannot run"],
            'unknown route' => [json_encode(['requests' => [['url' => '/api/v1/nosuch'] + $call]]), 'requests[0].url: \'/api/v1/nosuch\' is not a Gaia REST route'],
            'scalar body' => [json_encode(['requests' => [['body' => 'text'] + $call]]), 'requests[0].body must be a JSON object'],
        ];
    }

    /**
     * @dataProvider invalidBatchProvider
     */
    public function testInvalidBatchIsRefusedAsAWhole(string $rawBody, string $message): void
    {
        try {
            $this->validator()->validate($rawBody);
            $this->fail('Expected BadRequest');
        } catch (BadRequest $e) {
            $this->assertStringContainsString($message, $e->getMessage());
            $this->assertSame(400, $e->toResponse()->getStatusCode());
        }
    }

    public function testBodyOverTheSizeLimitIsRefusedBeforeDecoding(): void
    {
        $rawBody = $this->batch([$this->call('a')]);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('may not exceed 10 bytes');

        $this->validator(3, 10)->validate($rawBody);
    }

    public function testBatchAtTheLimitsIsAccepted(): void
    {
        $rawBody = $this->batch([$this->call('a'), $this->call('b'), $this->call('c')]);

        $this->assertCount(3, $this->validator(3, strlen($rawBody))->validate($rawBody));
    }
}
