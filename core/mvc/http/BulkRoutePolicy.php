<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Http;

/**
 * Decides whether one call may run inside a bulk batch.
 *
 * A call may run when its URL is a relative `/api/v1/<resource>...` path that
 * the REST router resolves for the method (the same path shapes
 * `Gaia\MVC\Router::init()` registers), and the resource is not refused:
 * the bulk resource itself (no nesting), resources with unauthenticated
 * actions (OAuth token, introspection, password reset, enrollment) and the
 * calls that stream files rather than JSON (upload bytes, user images).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Http
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BulkRoutePolicy
{
    public const PREFIX = '/api/v1/';

    /**
     * The bulk resource's own name: a batch may not hold another batch.
     */
    public const BULK_RESOURCE = 'bulk';

    /**
     * Calls whose request or response is a file stream, by resource, method
     * and number of path segments.
     *
     * @var array<string, array<string, int[]>>
     */
    private const STREAMING = [
        'upload' => ['GET' => [2], 'POST' => [1]],
        'userimage' => ['POST' => [1]],
    ];

    /**
     * Path segment counts each method has a route for (resource, id, relation).
     *
     * @var array<string, int[]>
     */
    private const SEGMENTS = [
        'GET' => [1, 2, 3],
        'POST' => [1],
        'PATCH' => [1, 2],
        'DELETE' => [1, 2],
    ];

    private const SEGMENT_PATTERN = '/\A[a-zA-Z0-9_-]+\z/';

    /**
     * @var array<string, string[]> resource => allowed methods
     */
    private $routes;

    /**
     * @var callable(string): bool
     */
    private $isAnonymous;

    /**
     * @param array<string, string[]> $routes the v1 REST routes: resource => allowed methods
     * @param callable(string): bool $isAnonymous whether a resource has actions that skip authentication
     */
    public function __construct(array $routes, callable $isAnonymous)
    {
        $this->routes = $routes;
        $this->isAnonymous = $isAnonymous;
    }

    /**
     * The policy for the configured v1 REST routes and their controllers.
     *
     * @param \Phalcon\Config\Config $settings the application settings
     * @return self
     */
    public static function fromSettings($settings): self
    {
        $routes = [];
        foreach ($settings->routes['rest']['v1'] as $resource => $route) {
            $routes[$resource] = $route->allowedMethods->toArray();
        }

        return new self($routes, [self::class, 'controllerIsAnonymous']);
    }

    /**
     * Whether the resource's controller lets any action skip authentication,
     * or there is no controller to run it.
     *
     * @param string $resource
     * @return bool
     */
    public static function controllerIsAnonymous(string $resource): bool
    {
        $class = 'Gaia\\MVC\\REST\\Controllers\\' . (new \Phalcon\Support\Helper\Str\Camelize())($resource) . 'Controller';
        if (!class_exists($class)) {
            return true;
        }

        $defaults = (new \ReflectionClass($class))->getDefaultProperties();

        return (isset($defaults['authorization']) && $defaults['authorization'] === false)
            || !empty($defaults['anonymousActions']);
    }

    /**
     * Why the call may not run in a batch; null when it may.
     *
     * @param string $method upper-case HTTP method
     * @param string $url the call's URL
     * @return string|null
     */
    public function refusal(string $method, string $url): ?string
    {
        $segments = $this->segments($url);
        if ($segments === null) {
            return "'{$url}' is not a relative " . self::PREFIX . '<resource> path';
        }

        $resource = $segments[0];
        if (!isset($this->routes[$resource])) {
            return "'{$url}' is not a Gaia REST route";
        }

        if ($resource === self::BULK_RESOURCE || ($this->isAnonymous)($resource)) {
            return "'{$resource}' cannot run in a bulk request";
        }

        $count = count($segments);
        if (!in_array($method, $this->routes[$resource], true)
            || !in_array($count, self::SEGMENTS[$method] ?? [], true)) {
            return "{$method} '{$url}' is not a Gaia REST route";
        }

        if (in_array($count, self::STREAMING[$resource][$method] ?? [], true)) {
            return "{$method} '{$url}' streams a file and cannot run in a bulk request";
        }

        return null;
    }

    /**
     * The path segments after the /api/v1/ prefix; null when the URL is not
     * a relative path of that form.
     *
     * @param string $url
     * @return string[]|null
     */
    private function segments(string $url): ?array
    {
        if (strpos($url, self::PREFIX) !== 0 || strpbrk($url, "#\r\n") !== false) {
            return null;
        }

        $path = (string) strstr($url . '?', '?', true);
        $segments = explode('/', rtrim(substr($path, strlen(self::PREFIX)), '/'));
        foreach ($segments as $segment) {
            if (preg_match(self::SEGMENT_PATTERN, $segment) !== 1) {
                return null;
            }
        }

        return $segments;
    }
}
