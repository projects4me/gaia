<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Tools;

/**
 * Lists the tools of an MCP server over the Streamable HTTP transport:
 * initialize, notifications/initialized, then tools/list page by page. It
 * owns the MCP transport (JSON-RPC over HTTP POST, JSON or event-stream
 * answers resumed with Last-Event-ID, the Mcp-Session-Id header); callers
 * get plain tool arrays.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Tools
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class McpToolLister
{
    /**
     * MCP protocol revision Gaia asks for. The MCP spec names its revisions
     * by release date (YYYY-MM-DD) and servers negotiate on that exact
     * string in initialize and the MCP-Protocol-Version header, so this is
     * the spec's identifier, not a Gaia version. 2025-11-25 is the newest
     * revision with the initialize handshake (2026-07-28 drops it).
     *
     * @see https://modelcontextprotocol.io/specification/versioning
     */
    public const PROTOCOL_VERSION = '2025-11-25';

    /**
     * Stop following nextCursor after this many pages.
     */
    private const MAX_PAGES = 50;

    /**
     * Reconnect to a closed event stream at most this many times per request.
     */
    private const MAX_RESUMES = 5;

    /**
     * Longest wait (ms) before reconnecting, whatever the server's retry asks.
     */
    private const MAX_RETRY_MS = 2000;

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
     * Protocol version the server agreed to on initialize, sent on later
     * requests in the MCP-Protocol-Version header. Null until initialize
     * has answered: the header is not sent on initialize itself, so a server
     * on an older revision can still negotiate down.
     *
     * @var string|null
     */
    private $protocolVersion;

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
        $this->protocolVersion = null;
        $initialized = $this->call($endpoint, 'initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'gaia', 'version' => '1.0'],
        ]);
        $this->protocolVersion = isset($initialized->protocolVersion) && is_string($initialized->protocolVersion) && $initialized->protocolVersion !== ''
            ? $initialized->protocolVersion
            : self::PROTOCOL_VERSION;
        $this->notify($endpoint, 'notifications/initialized');

        $tools = [];
        $cursor = null;
        $seenCursors = [];
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
            if (isset($seenCursors[$cursor])) {
                throw new ToolRefreshException('The tool server repeated a pagination cursor.');
            }
            $seenCursors[$cursor] = true;
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
        $this->assertSuccess($response, $method);

        $sessionId = $response->getHeaderLine('Mcp-Session-Id');
        if ($sessionId !== '') {
            $this->sessionId = $sessionId;
        }

        $message = $this->findResponse($endpoint, $response, $method, $id);
        if (isset($message->error)) {
            $error = is_object($message->error) && isset($message->error->message) ? (string) $message->error->message : 'unknown error';
            $this->log('The tool server refused ' . $method . ': ' . $error);
            throw new ToolRefreshException('The tool server refused ' . $method . '.');
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
        $this->assertSuccess($response, $method);
    }

    /**
     * Stop the refresh on a non-2xx answer.
     *
     * @param  \Psr\Http\Message\ResponseInterface $response
     * @param  string                              $method
     * @return void
     * @throws ToolRefreshException
     */
    private function assertSuccess($response, $method)
    {
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
        return $this->send('POST', $endpoint, ['Accept' => 'application/json, text/event-stream', 'Content-Type' => 'application/json'], json_encode($message));
    }

    /**
     * Send one HTTP request with the session and protocol headers.
     *
     * @param  string      $httpMethod
     * @param  string      $endpoint
     * @param  array       $headers
     * @param  string|null $body
     * @return \Psr\Http\Message\ResponseInterface
     * @throws ToolRefreshException
     */
    private function send($httpMethod, $endpoint, array $headers, $body = null)
    {
        if ($this->protocolVersion !== null) {
            $headers['MCP-Protocol-Version'] = $this->protocolVersion;
        }
        if ($this->sessionId !== null) {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }

        $options = ['headers' => $headers, 'http_errors' => false];
        if ($body !== null) {
            $options['body'] = $body;
        }

        try {
            return $this->client()->request($httpMethod, $endpoint, $options);
        } catch (\Throwable $e) {
            $this->log('The tool server at ' . $this->redactUrls($endpoint) . ' could not be reached: ' . $this->redactUrls($e->getMessage()));
            throw new ToolRefreshException('The tool server could not be reached.');
        }
    }

    /**
     * The JSON-RPC response with this id, from a JSON body or an event
     * stream. A stream the server closes before answering (2025-11-25
     * polling) is resumed with GET and Last-Event-ID.
     *
     * @param  string                              $endpoint
     * @param  \Psr\Http\Message\ResponseInterface $response
     * @param  string                              $method
     * @param  int                                 $id
     * @return object
     * @throws ToolRefreshException
     */
    private function findResponse($endpoint, $response, $method, $id)
    {
        if (stripos($response->getHeaderLine('Content-Type'), 'text/event-stream') === false) {
            return $this->matching([json_decode((string) $response->getBody())], $id);
        }

        $lastEventId = null;
        for ($resumes = 0; ; $resumes++) {
            $stream = $this->eventStream((string) $response->getBody());
            $message = $this->matching($stream['messages'], $id);
            if ($message !== null) {
                return $message;
            }

            $lastEventId = $stream['lastEventId'] !== null ? $stream['lastEventId'] : $lastEventId;
            if ($lastEventId === null || $resumes >= self::MAX_RESUMES) {
                break;
            }

            if ($stream['retry'] !== null) {
                usleep(min($stream['retry'], self::MAX_RETRY_MS) * 1000);
            }
            $response = $this->send('GET', $endpoint, ['Accept' => 'text/event-stream', 'Last-Event-ID' => $lastEventId]);
            $this->assertSuccess($response, $method);
            if (stripos($response->getHeaderLine('Content-Type'), 'text/event-stream') === false) {
                break;
            }
        }

        throw new ToolRefreshException('The tool server sent no JSON-RPC response.');
    }

    /**
     * The message answering this id, if any.
     *
     * @param  array $messages
     * @param  int   $id
     * @return object|null
     */
    private function matching(array $messages, $id)
    {
        foreach ($messages as $message) {
            if (is_object($message) && isset($message->id) && (int) $message->id === $id) {
                return $message;
            }
        }

        return null;
    }

    /**
     * The JSON messages in the data lines of an event stream, the last event
     * id and the last retry (ms) the server asked for.
     *
     * @param  string $body
     * @return array ['messages' => array, 'lastEventId' => string|null, 'retry' => int|null]
     */
    private function eventStream($body)
    {
        $messages = [];
        $lastEventId = null;
        $retry = null;
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
            } elseif (strpos($line, 'id:') === 0) {
                $value = ltrim(substr($line, 3), ' ');
                $lastEventId = $value !== '' ? $value : null;
            } elseif (strpos($line, 'retry:') === 0 && ctype_digit(ltrim(substr($line, 6), ' '))) {
                $retry = (int) ltrim(substr($line, 6), ' ');
            }
        }

        return ['messages' => $messages, 'lastEventId' => $lastEventId, 'retry' => $retry];
    }

    /**
     * The text with every URL cut down to scheme, host, port and path, so
     * credentials in userinfo or the query never reach the log.
     *
     * @param  string $text
     * @return string
     */
    private function redactUrls($text)
    {
        return preg_replace_callback('#[a-z][a-z0-9+.-]*://[^\s"\'<>]+#i', function ($match) {
            $parts = parse_url($match[0]);
            if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
                return '[url]';
            }

            return $parts['scheme'] . '://' . $parts['host']
                . (isset($parts['port']) ? ':' . $parts['port'] : '')
                . (isset($parts['path']) ? $parts['path'] : '');
        }, (string) $text);
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
     * Log what the server or transport said. The details stay in the server
     * log: the exception message reaches the API response and refreshError.
     *
     * @param  string $message
     * @return void
     */
    private function log($message)
    {
        global $logger;
        if (isset($logger)) {
            $logger->error($message);
        }
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
