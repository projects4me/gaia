<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph;

/**
 * Finds the route a concrete request path (an api-tester case path such as
 * `/api/v1/issue/{issueId}?x=1`) hits.
 *
 * A route segment written `{name}` matches any one segment; literal segments
 * must be equal. Among matching routes the one with the most literal segments
 * wins, as the router prefers a literal route over a captured one.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RouteMatcher
{
    /**
     * @var array[] [key, method, segments]
     */
    private $routes = [];

    /**
     * @param string $key the route node key
     * @param string|null $method HTTP method, or ANY
     * @param string|null $path route pattern; routes without a path never match
     */
    public function add(string $key, $method, $path)
    {
        if ($path === null) {
            return;
        }
        $this->routes[] = [$key, $method, self::segments($path)];
    }

    /**
     * @param string $method
     * @param string $path
     * @return string|null the route node key
     */
    public function match(string $method, string $path)
    {
        $segments = self::segments(strtok($path, '?'));
        $best = null;
        $bestScore = -1;

        foreach ($this->routes as list($key, $routeMethod, $routeSegments)) {
            if (($routeMethod !== 'ANY' && $routeMethod !== $method) || count($routeSegments) !== count($segments)) {
                continue;
            }
            $score = self::score($routeSegments, $segments);
            if ($score > $bestScore) {
                $best = $key;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @param string[] $routeSegments
     * @param string[] $segments
     * @return int literal segments matched, or -1 when the path does not match
     */
    private static function score(array $routeSegments, array $segments): int
    {
        $score = 0;
        foreach ($routeSegments as $i => $routeSegment) {
            if (preg_match('/^\{[^}]+\}$/', $routeSegment)) {
                continue;
            }
            if ($routeSegment !== $segments[$i]) {
                return -1;
            }
            $score++;
        }

        return $score;
    }

    /**
     * @param string $path
     * @return string[]
     */
    private static function segments(string $path): array
    {
        $path = trim($path, '/');

        return $path === '' ? [] : explode('/', $path);
    }
}
