<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Tools;

/**
 * Lists the tools of an MCP server over the Streamable HTTP transport:
 * initialize, notifications/initialized, then tools/list page by page. It
 * owns the MCP transport (JSON-RPC over HTTP POST, JSON or event-stream
 * answers, the Mcp-Session-Id header); callers get plain tool arrays.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Tools
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class McpToolLister
{
    /**
     * MCP protocol version Gaia asks for.
     */
    public const PROTOCOL_VERSION = '2025-06-18';

    /**
     * Stop following nextCursor after this many pages.
     */
    private const MAX_PAGES = 50;

    /**
     * @var \GuzzleHttp\ClientInterface|null
     */
    private $httpClient;

    /**
     * Session id the server gave on initialize, sent on later requests.
     *
     * @var string|null
     */
    private $sessionId;

    /**
     * Next JSON-RPC request id.
     *
     * @var int
     */
    private $nextId = 1;

    /**
     * @param \GuzzleHttp\ClientInterface|null $httpClient A test client, else a Guzzle client with timeouts
     */
    public function __construct($httpClient = null)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * The tools the server lists.
     *
     * @param  string $endpoint The server's MCP URL
     * @return array List of ['name' => string, 'description' => string|null, 'inputSchema' => string|null (JSON)]
     * @throws ToolRefreshException
     */
    public function listTools($endpoint)
    {
        $this->sessionId = null;
        $this->call($endpoint, 'initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'gaia', 'version' => '1.0'],
        ]);
        $this->notify($endpoint, 'notifications/initialized');

        $tools = [];
        $cursor = null;
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $result = $this->call($endpoint, 'tools/list', $cursor === null ? new \stdClass() : ['cursor' => $cursor]);
            if (!isset($result->tools) || !is_array($result->tools)) {
                throw new ToolRefreshException('The tool server did not return a tool list.');
            }
            foreach ($result->tools as $tool) {
                $tools[] = $this->normalize($tool);
            }

            $cursor = isset($result->nextCursor) && (string) $result->nextCursor !== '' ? (string) $result->nextCursor : null;
            if ($cursor === null) {
                return $tools;
            }
        }

        throw new ToolRefreshException('The tool server returned more than ' . self::MAX_PAGES . ' pages of tools.');
    }

    /**
     * Send a JSON-RPC request and return its result.
     *
     * @param  string       $endpoint
     * @param  string       $method
     * @param  array|object $params
     * @return object
     * @throws ToolRefreshException
     */
    private function call($endpoint, $method, $params)
    {
        $id = $this->nextId++;
        $response = $this->post($endpoint, ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);

        $status = (int) $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new ToolRefreshException('The tool server answered ' . $method . ' with HTTP ' . $status . '.');
        }

        $sessionId = $response->getHeaderLine('Mcp-Session-Id');
        if ($sessionId !== '') {
            $this->sessionId = $sessionId;
        }

        $message = $this->findResponse((string) $response->getBody(), $response->getHeaderLine('Content-Type'), $id);
        if (isset($message->error)) {
            $error = is_object($message->error) && isset($message->error->message) ? (string) $message->error->message : 'unknown error';
            throw new ToolRefreshException('The tool server refused ' . $method . ': ' . $error);
        }
        if (!isset($message->result) || !is_object($message->result)) {
            throw new ToolRefreshException('The tool server sent no result for ' . $method . '.');
        }

        return $message->result;
    }

    /**
     * Send a JSON-RPC notification (no answer expected).
     *
     * @param  string $endpoint
     * @param  string $method
     * @return void
     * @throws ToolRefreshException
     */
    private function notify($endpoint, $method)
    {
        $response = $this->post($endpoint, ['jsonrpc' => '2.0', 'method' => $method]);
        $status = (int) $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw new ToolRefreshException('The tool server answered ' . $method . ' with HTTP ' . $status . '.');
        }
    }

    /**
     * POST one JSON-RPC message.
     *
     * @param  string $endpoint
     * @param  array  $message
     * @return \Psr\Http\Message\ResponseInterface
     * @throws ToolRefreshException
     */
    private function post($endpoint, array $message)
    {
        $headers = [
            'Accept' => 'application/json, text/event-stream',
            'Content-Type' => 'application/json',
            'MCP-Protocol-Version' => self::PROTOCOL_VERSION,
        ];
        if ($this->sessionId !== null) {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }

        try {
            return $this->client()->request('POST', $endpoint, [
                'headers' => $headers,
                'body' => json_encode($message),
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            throw new ToolRefreshException('The tool server could not be reached: ' . $e->getMessage());
        }
    }

    /**
     * The JSON-RPC response with this id, from a JSON body or an event stream.
     *
     * @param  string $body
     * @param  string $contentType
     * @param  int    $id
     * @return object
     * @throws ToolRefreshException
     */
    private function findResponse($body, $contentType, $id)
    {
        $messages = stripos($contentType, 'text/event-stream') !== false
            ? $this->eventStreamMessages($body)
            : [json_decode($body)];

        foreach ($messages as $message) {
            if (is_object($message) && isset($message->id) && (int) $message->id === $id) {
                return $message;
            }
        }

        throw new ToolRefreshException('The tool server sent no JSON-RPC response.');
    }

    /**
     * The JSON messages in the data lines of an event stream.
     *
     * @param  string $body
     * @return array
     */
    private function eventStreamMessages($body)
    {
        $messages = [];
        $data = [];
        foreach (preg_split('/\r\n|\n|\r/', $body . "\n\n") as $line) {
            if ($line === '') {
                if ($data) {
                    $messages[] = json_decode(implode("\n", $data));
                    $data = [];
                }
                continue;
            }
            if (strpos($line, 'data:') === 0) {
                $data[] = ltrim(substr($line, 5), ' ');
            }
        }

        return $messages;
    }

    /**
     * One listed tool as the catalog stores it.
     *
     * @param  mixed $tool
     * @return array
     * @throws ToolRefreshException
     */
    private function normalize($tool)
    {
        if (!is_object($tool) || !isset($tool->name) || !is_string($tool->name) || $tool->name === '') {
            throw new ToolRefreshException('The tool server listed a tool without a name.');
        }

        return [
            'name' => $tool->name,
            'description' => isset($tool->description) && is_string($tool->description) ? $tool->description : null,
            'inputSchema' => isset($tool->inputSchema) && is_object($tool->inputSchema)
                ? json_encode($tool->inputSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
        ];
    }

    /**
     * @return \GuzzleHttp\ClientInterface
     */
    private function client()
    {
        if ($this->httpClient === null) {
            $this->httpClient = new \GuzzleHttp\Client([
                'timeout' => 10.0,
                'connect_timeout' => 3.0,
                'allow_redirects' => false,
            ]);
        }

        return $this->httpClient;
    }
}
