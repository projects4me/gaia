<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Services;

use Gaia\Exception\EmbeddingsUnavailableException;
use Gaia\Services\Embeddings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Ares embeddings client, with the HTTP layer mocked: no
 * network is used.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class EmbeddingsTest extends TestCase
{
    private array $history = [];

    private function service(array $queue): Embeddings
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));
        return new Embeddings(new Client(['handler' => $stack]));
    }

    private function body(array $vector, array $extra = []): Response
    {
        return new Response(200, [], json_encode(array_merge(
            ['model' => 'nomic-embed-text', 'dimensions' => count($vector), 'embeddings' => [$vector]],
            $extra
        )));
    }

    public function testReturnsTheVectorAndSendsAQueryTask(): void
    {
        $vector = array_fill(0, 768, 0.5);
        $result = $this->service([$this->body($vector)])->embedQuery('find me');

        $this->assertSame($vector, $result);
        $request = $this->history[0]['request'];
        $this->assertSame('/embeddings', $request->getUri()->getPath());
        $this->assertSame(['texts' => ['find me'], 'task' => 'query'], json_decode((string) $request->getBody(), true));
    }

    /**
     * @dataProvider failures
     */
    public function testFailuresThrow(array $queue, string $message): void
    {
        $this->expectException(EmbeddingsUnavailableException::class);
        $this->expectExceptionMessage($message);
        $this->service($queue)->embedQuery('x');
    }

    public static function failures(): array
    {
        $request = new Request('POST', 'http://ares/embeddings');
        return [
            '422' => [[new Response(422, [], '{"detail":"bad"}')], 'status 422'],
            '503' => [[new Response(503)], 'status 503'],
            'timeout' => [[new ConnectException('cURL error 28: timed out', $request)], 'no response'],
            'not json' => [[new Response(200, [], 'nope')], 'malformed'],
            'no embeddings' => [[new Response(200, [], '{"model":"m"}')], 'malformed'],
            'two vectors' => [[new Response(200, [], json_encode(['embeddings' => [[1], [2]]]))], 'malformed'],
            'wrong dimensions' => [[new Response(200, [], json_encode(['embeddings' => [[0.1, 0.2]]]))], '2 dimensions'],
            'empty vector' => [[new Response(200, [], json_encode(['embeddings' => [[]]]))], '0 dimensions'],
            'non numeric' => [[new Response(200, [], json_encode(['embeddings' => [array_fill(0, 768, 'a')]]))], 'non-numeric'],
        ];
    }
}
