<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Tools;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Gaia\Libraries\Tools\McpToolLister;
use Gaia\Libraries\Tools\ToolRefreshException;
use PHPUnit\Framework\TestCase;

/**
 * Tests listing an MCP server's tools over Streamable HTTP: the
 * initialize handshake, JSON and event-stream answers, the session header,
 * pagination and the failures that stop a refresh.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class McpToolListerTest extends TestCase
{
    private const ENDPOINT = 'http://mcp.test/mcp';

    /**
     * Requests the mock client received.
     *
     * @var array
     */
    private $history = [];

    public function testListsToolsAfterTheHandshake()
    {
        $lister = $this->lister([
            $this->json(1, ['protocolVersion' => McpToolLister::PROTOCOL_VERSION, 'capabilities' => new \stdClass()]),
            new Response(202),
            $this->json(2, ['tools' => [
                ['name' => 'gaia_get_issue', 'description' => 'Read an issue', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
                ['name' => 'gaia_whoami', 'inputSchema' => ['type' => 'object']],
            ]]),
        ]);

        $tools = $lister->listTools(self::ENDPOINT);

        $this->assertSame([
            ['name' => 'gaia_get_issue', 'description' => 'Read an issue', 'inputSchema' => '{"type":"object","properties":{}}'],
            ['name' => 'gaia_whoami', 'description' => null, 'inputSchema' => '{"type":"object"}'],
        ], $tools);
        $this->assertSame(['initialize', 'notifications/initialized', 'tools/list'], $this->sentMethods());
    }

    public function testReadsEventStreamAnswersAndSendsTheSessionId()
    {
        $lister = $this->lister([
            new Response(200, ['Content-Type' => 'application/json', 'Mcp-Session-Id' => 'session-1'], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => new \stdClass()])),
            new Response(202),
            new Response(200, ['Content-Type' => 'text/event-stream'], "event: message\ndata: {\"jsonrpc\":\"2.0\",\"method\":\"notifications/progress\"}\n\nevent: message\ndata: " . json_encode(['jsonrpc' => '2.0', 'id' => 2, 'result' => ['tools' => [['name' => 'echo']]]]) . "\n\n"),
        ]);

        $tools = $lister->listTools(self::ENDPOINT);

        $this->assertSame('echo', $tools[0]['name']);
        $this->assertSame('', $this->history[0]['request']->getHeaderLine('Mcp-Session-Id'));
        $this->assertSame('session-1', $this->history[1]['request']->getHeaderLine('Mcp-Session-Id'));
        $this->assertSame('session-1', $this->history[2]['request']->getHeaderLine('Mcp-Session-Id'));
    }

    public function testSendsTheNegotiatedProtocolVersion()
    {
        $lister = $this->lister([
            $this->json(1, ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass()]),
            new Response(202),
            $this->json(2, ['tools' => []]),
        ]);

        $lister->listTools(self::ENDPOINT);

        $initialize = json_decode((string) $this->history[0]['request']->getBody(), true);
        $this->assertSame(McpToolLister::PROTOCOL_VERSION, $initialize['params']['protocolVersion']);
        $this->assertFalse($this->history[0]['request']->hasHeader('MCP-Protocol-Version'));
        $this->assertSame('2025-06-18', $this->history[1]['request']->getHeaderLine('MCP-Protocol-Version'));
        $this->assertSame('2025-06-18', $this->history[2]['request']->getHeaderLine('MCP-Protocol-Version'));
    }

    public function testResumesAClosedEventStreamWithLastEventId()
    {
        $lister = $this->lister([
            $this->json(1, new \stdClass()),
            new Response(202),
            new Response(200, ['Content-Type' => 'text/event-stream'], "id: evt-1\nretry: 0\ndata:\n\n"),
            new Response(200, ['Content-Type' => 'text/event-stream'], "id: evt-2\ndata: " . json_encode(['jsonrpc' => '2.0', 'id' => 2, 'result' => ['tools' => [['name' => 'echo']]]]) . "\n\n"),
        ]);

        $tools = $lister->listTools(self::ENDPOINT);

        $this->assertSame('echo', $tools[0]['name']);
        $resume = $this->history[3]['request'];
        $this->assertSame('GET', $resume->getMethod());
        $this->assertSame('evt-1', $resume->getHeaderLine('Last-Event-ID'));
        $this->assertSame('text/event-stream', $resume->getHeaderLine('Accept'));
    }

    public function testAStreamClosedWithoutAnEventIdStopsTheRefresh()
    {
        $lister = $this->lister([
            new Response(200, ['Content-Type' => 'text/event-stream'], "data:\n\n"),
        ]);

        $this->expectException(ToolRefreshException::class);
        $this->expectExceptionMessage('The tool server sent no JSON-RPC response.');
        $lister->listTools(self::ENDPOINT);
    }

    public function testTheLogNeverHoldsUrlCredentials()
    {
        global $logger;
        $previous = $logger;
        $logger = new class {
            public $lines = [];

            public function error($message)
            {
                $this->lines[] = $message;
            }
        };
        $endpoint = 'https://user:secret@mcp.test:8443/mcp/path-token?token=abc';
        $lister = $this->lister([
            new \GuzzleHttp\Exception\ConnectException('Failed to connect for ' . $endpoint, new \GuzzleHttp\Psr7\Request('POST', $endpoint)),
        ]);

        try {
            $lister->listTools($endpoint);
            $this->fail('The refresh should have stopped.');
        } catch (ToolRefreshException $e) {
            $this->assertSame(
                ['The tool server at https://mcp.test:8443 could not be reached: Failed to connect for https://mcp.test:8443'],
                $logger->lines
            );
        } finally {
            $logger = $previous;
        }
    }

    public function testFollowsNextCursor()
    {
        $lister = $this->lister([
            $this->json(1, new \stdClass()),
            new Response(202),
            $this->json(2, ['tools' => [['name' => 'one']], 'nextCursor' => 'page-2']),
            $this->json(3, ['tools' => [['name' => 'two']]]),
        ]);

        $tools = $lister->listTools(self::ENDPOINT);

        $this->assertSame(['one', 'two'], array_column($tools, 'name'));
        $lastBody = json_decode((string) $this->history[3]['request']->getBody(), true);
        $this->assertSame(['cursor' => 'page-2'], $lastBody['params']);
    }

    public function testARepeatedCursorStopsTheRefresh()
    {
        $lister = $this->lister([
            $this->json(1, new \stdClass()),
            new Response(202),
            $this->json(2, ['tools' => [['name' => 'one']], 'nextCursor' => 'page-2']),
            $this->json(3, ['tools' => [['name' => 'two']], 'nextCursor' => 'page-2']),
        ]);

        $this->expectException(ToolRefreshException::class);
        $this->expectExceptionMessage('The tool server repeated a pagination cursor.');
        $lister->listTools(self::ENDPOINT);
    }

    public function testARefreshPastItsTimeBudgetStops()
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler([
            $this->json(1, new \stdClass()),
            new Response(202),
            $this->json(2, ['tools' => [['name' => 'one']]]),
        ]));
        $stack->push(Middleware::history($this->history));
        $lister = new McpToolLister(new Client(['handler' => $stack]), -1);

        $this->expectException(ToolRefreshException::class);
        $this->expectExceptionMessage('The tool server took too long to answer.');
        $lister->listTools(self::ENDPOINT);
    }

    public function testAJsonRpcErrorStopsTheRefresh()
    {
        $lister = $this->lister([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['code' => -32600, 'message' => 'Unsupported protocol version']])),
        ]);

        $this->expectException(ToolRefreshException::class);
        $this->expectExceptionMessage('The tool server refused initialize.');
        $lister->listTools(self::ENDPOINT);
    }

    public function testAnHttpErrorStopsTheRefresh()
    {
        $lister = $this->lister([new Response(401)]);

        $this->expectException(ToolRefreshException::class);
        $this->expectExceptionMessage('The tool server answered initialize with HTTP 401.');
        $lister->listTools(self::ENDPOINT);
    }

    public function testAnUnreachableServerStopsTheRefresh()
    {
        $lister = $this->lister([
            new \GuzzleHttp\Exception\ConnectException('Connection refused', new \GuzzleHttp\Psr7\Request('POST', self::ENDPOINT)),
        ]);

        $this->expectException(ToolRefreshException::class);
        $this->expectExceptionMessage('The tool server could not be reached.');
        $lister->listTools(self::ENDPOINT);
    }

    public function testAToolWithoutANameStopsTheRefresh()
    {
        $lister = $this->lister([
            $this->json(1, new \stdClass()),
            new Response(202),
            $this->json(2, ['tools' => [['description' => 'nameless']]]),
        ]);

        $this->expectException(ToolRefreshException::class);
        $this->expectExceptionMessage('The tool server listed a tool without a name.');
        $lister->listTools(self::ENDPOINT);
    }

    /**
     * A lister over a mock client that answers in order.
     *
     * @param  array $responses
     * @return McpToolLister
     */
    private function lister(array $responses)
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new McpToolLister(new Client(['handler' => $stack]));
    }

    /**
     * A JSON-RPC result answered as application/json.
     *
     * @param  int          $id
     * @param  array|object $result
     * @return Response
     */
    private function json($id, $result)
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result])
        );
    }

    /**
     * The JSON-RPC methods sent, in order.
     *
     * @return array
     */
    private function sentMethods()
    {
        return array_map(function ($entry) {
            return json_decode((string) $entry['request']->getBody(), true)['method'];
        }, $this->history);
    }
}
