<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

/**
 * Extracts the routes from `core/config/routes.php`, one record per path,
 * method and action.
 *
 * REST resources are expanded the way `Gaia\MVC\Router::init()` registers them:
 * each allowed method becomes its item and collection routes (GET also gets
 * `related`), and every resource gets an OPTIONS route. Captured segments are
 * written as `{identifier}` and `{relation}`. App routes match any method
 * (`ANY`); system routes have no path or method.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RouteExtractor
{
    public const SOURCE = 'core/config/routes.php';

    /**
     * Routes registered per allowed REST method: [method, path suffix, action].
     */
    private const REST_ROUTES = [
        'GET' => [
            ['GET', '/{id}', 'get'],
            ['GET', '/{id}/{relation}', 'related'],
            ['GET', '', 'list'],
        ],
        'POST' => [
            ['POST', '', 'post'],
        ],
        'PUT' => [
            ['PUT', '/{id}', 'put'],
            ['PUT', '', 'putCollection'],
        ],
        'PATCH' => [
            ['PATCH', '/{id}', 'patch'],
            ['PATCH', '', 'patchCollection'],
        ],
        'DELETE' => [
            ['DELETE', '/{id}', 'delete'],
            ['DELETE', '', 'deleteCollection'],
        ],
    ];

    /**
     * @var string
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @return array[] route records with the keys type, name, version, method,
     *  path, controller and action
     */
    public function extract(): array
    {
        $config = SourceFile::readPhpArray($this->root . '/' . self::SOURCE);
        $routes = isset($config['routes']) ? $config['routes'] : [];

        return array_merge(
            $this->restRoutes(isset($routes['rest']) ? $routes['rest'] : []),
            $this->namedRoutes('app', isset($routes['app']) ? $routes['app'] : []),
            $this->namedRoutes('system', isset($routes['system']) ? $routes['system'] : [])
        );
    }

    /**
     * @param array $byVersion
     * @return array[]
     */
    private function restRoutes(array $byVersion): array
    {
        $records = [];

        foreach ($byVersion as $version => $resources) {
            foreach ($resources as $module => $resource) {
                $base = '/' . ltrim(preg_replace('/:version/i', $version, $resource['path']), '/');
                $identifier = '{' . (isset($resource['identifier']) ? $resource['identifier'] : 'id') . '}';
                $methods = isset($resource['allowedMethods']) ? $resource['allowedMethods'] : [];

                foreach ($methods as $allowed) {
                    $expansions = array_key_exists($allowed, self::REST_ROUTES) ? self::REST_ROUTES[$allowed] : [];
                    foreach ($expansions as list($method, $suffix, $action)) {
                        $path = $base . str_replace('{id}', $identifier, $suffix);
                        $records[] = $this->record('rest', $module, $version, $method, $path, $module, $action);
                    }
                }

                $records[] = $this->record('rest', $module, $version, 'OPTIONS', $base, $module, 'options');
            }
        }

        return $records;
    }

    /**
     * @param string $type app or system
     * @param array $routes
     * @return array[]
     */
    private function namedRoutes(string $type, array $routes): array
    {
        $records = [];

        foreach ($routes as $name => $route) {
            $path = isset($route['path']) ? $route['path'] : null;
            $method = $path === null ? null : (isset($route['method']) ? strtoupper($route['method']) : 'ANY');
            $records[] = $this->record($type, $name, null, $method, $path, $route['controller'], $route['action']);
        }

        return $records;
    }

    /**
     * @return array
     */
    private function record(
        string $type,
        string $name,
        $version,
        $method,
        $path,
        string $controller,
        string $action
    ): array {
        return [
            'type' => $type,
            'name' => $name,
            'version' => $version,
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action,
        ];
    }
}
