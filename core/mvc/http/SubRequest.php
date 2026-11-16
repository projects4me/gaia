<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Http;

/**
 * One REST call run inside the current PHP request (see SubRequestRunner).
 *
 * The request carries its own method, path, query parameters, JSON body and
 * Authorization header. Phalcon's getMethod(), getURI() and getHeader() are
 * final and read $_SERVER, so the runner overlays the superglobals with
 * superglobals() while the call runs; the body cannot come from php://input
 * (that is the outer request's body), so getRawBody() and getJsonRawBody()
 * return the given body.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Http
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SubRequest extends \Phalcon\Http\Request implements OAuthRequestSource
{
    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $path;

    /**
     * @var array
     */
    private $query;

    /**
     * @var array|\stdClass|null
     */
    private $body;

    /**
     * @var string
     */
    private $authorization;

    /**
     * @param string $method HTTP method, any case
     * @param string $path URI path without the query string
     * @param array $query query parameters, as parse_str() gives them
     * @param array|\stdClass|null $body the decoded JSON body (objects as
     *   stdClass keep {} apart from [] in getRawBody()); null for none
     * @param string $authorization the Authorization header value
     */
    public function __construct(string $method, string $path, array $query, $body, string $authorization)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->query = $query;
        $this->body = $body;
        $this->authorization = $authorization;
    }

    /**
     * Build a sub-request from a URL that may carry a query string.
     *
     * @param string $method
     * @param string $url path with an optional query string, e.g. /api/v1/issue?query=...
     * @param array|\stdClass|null $body
     * @param string $authorization
     * @return self
     */
    public static function fromUrl(string $method, string $url, $body, string $authorization): self
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $query = [];
        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?? ''), $query);

        return new self($method, $path, $query, $body, $authorization);
    }

    /**
     * The URI path the router matches, without the query string.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * The values the superglobals hold while this call runs: the outer
     * request's server variables with this call's method, URI, content type
     * and Authorization header; the query as $_GET and $_REQUEST; no $_POST
     * or $_FILES (the body is JSON) and no cookies.
     *
     * @param array $outerServer the outer request's $_SERVER
     * @return array keys _SERVER, _GET, _POST, _REQUEST, _FILES, _COOKIE
     */
    public function superglobals(array $outerServer): array
    {
        $server = $outerServer;
        unset(
            $server['HTTP_AUTHORIZATION'],
            $server['REDIRECT_HTTP_AUTHORIZATION'],
            $server['PHP_AUTH_USER'],
            $server['PHP_AUTH_PW'],
            $server['PHP_AUTH_DIGEST'],
            $server['HTTP_X_HTTP_METHOD_OVERRIDE'],
            $server['CONTENT_LENGTH'],
            $server['HTTP_CONTENT_LENGTH']
        );
        $queryString = http_build_query($this->query);
        $server['REQUEST_METHOD'] = $this->method;
        $server['REQUEST_URI'] = $this->path . ($queryString === '' ? '' : '?' . $queryString);
        $server['QUERY_STRING'] = $queryString;
        unset($server['CONTENT_TYPE'], $server['HTTP_CONTENT_TYPE']);
        if ($this->body !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
            $server['HTTP_CONTENT_TYPE'] = 'application/json';
        }
        if ($this->authorization !== '') {
            $server['HTTP_AUTHORIZATION'] = $this->authorization;
        }

        // The rewrite rule passes the routed path as _url (buildHAL() builds
        // its links from it); it is not part of the public query string.
        $get = ['_url' => $this->path] + $this->query;

        return [
            '_SERVER' => $server,
            '_GET' => $get,
            '_POST' => [],
            '_REQUEST' => $get,
            '_FILES' => [],
            '_COOKIE' => [],
        ];
    }

    /**
     * The OAuth2 request for this call, from its own query, body and headers
     * and no cookies (createFromGlobals() would read the outer request's body
     * from php://input). Read while the superglobals are overlaid. The
     * headers are passed explicitly: with no Authorization in $_SERVER the
     * OAuth2 library falls back to apache_request_headers(), which returns
     * the outer request's token.
     *
     * @return \OAuth2\Request
     */
    public function toOAuthRequest(): \OAuth2\Request
    {
        // Same body handling as createFromGlobals() for a JSON request.
        $parameters = in_array($this->method, ['POST', 'PUT', 'DELETE'], true)
            ? ($this->getJsonRawBody(true) ?: [])
            : [];

        return new \OAuth2\Request(
            $this->query,
            $parameters,
            [],
            [],
            [],
            $_SERVER,
            $this->getRawBody(),
            $this->oauthHeaders()
        );
    }

    /**
     * The headers the OAuth2 library reads: this call's Authorization (empty
     * when it has none) and, with a body, its content type.
     *
     * @return array
     */
    private function oauthHeaders(): array
    {
        $headers = ['AUTHORIZATION' => $this->authorization] + $this->basicCredentials();
        if ($this->body !== null) {
            $headers['CONTENT_TYPE'] = 'application/json';
        }

        return $headers;
    }

    /**
     * The client credentials of a Basic Authorization, as the OAuth2 library
     * reads them (PHP_AUTH_USER, PHP_AUTH_PW); empty for any other value.
     * With explicit headers the library does not derive them itself.
     *
     * @return array
     */
    private function basicCredentials(): array
    {
        if (stripos($this->authorization, 'basic ') !== 0) {
            return [];
        }
        $decoded = base64_decode(trim(substr($this->authorization, 6)), true);
        if ($decoded === false || strpos($decoded, ':') === false) {
            return [];
        }
        [$user, $password] = explode(':', $decoded, 2);

        return ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => $password];
    }

    /**
     * The body as the JSON text a client would have sent.
     *
     * @return string
     */
    public function getRawBody(): string
    {
        return $this->body === null ? '' : (string) json_encode($this->body);
    }

    /**
     * The decoded body, like Phalcon's: false when there is no body.
     *
     * @param bool $associative arrays instead of stdClass objects
     * @return \stdClass|array|bool
     */
    public function getJsonRawBody(bool $associative = false)
    {
        $raw = $this->getRawBody();
        if ($raw === '') {
            return false;
        }

        return json_decode($raw, $associative);
    }

    /**
     * The decoded body, null when there is none.
     *
     * @return array|\stdClass|null
     */
    public function body()
    {
        return $this->body;
    }
}
