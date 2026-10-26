<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\RouteExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the route extractor (agaia-4128).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RouteExtractorTest extends TestCase
{
    /**
     * @return string[] `METHOD path -> controller.action` per route
     */
    private function summaries(): array
    {
        $summaries = [];
        foreach ((new RouteExtractor(__DIR__ . '/fixtures/repo'))->extract() as $route) {
            $summaries[] = "{$route['type']} {$route['method']} {$route['path']} -> {$route['controller']}.{$route['action']}";
        }

        return $summaries;
    }

    public function testExpandsRestResourcesLikeTheRouter()
    {
        $this->assertSame([
            'rest GET /api/v1/widget/{id} -> widget.get',
            'rest GET /api/v1/widget/{id}/{relation} -> widget.related',
            'rest GET /api/v1/widget -> widget.list',
            'rest POST /api/v1/widget -> widget.post',
            'rest OPTIONS /api/v1/widget -> widget.options',
        ], array_slice($this->summaries(), 0, 5));
    }

    public function testAppRoutesMatchAnyMethodAndSystemRoutesHaveNoPath()
    {
        $this->assertSame([
            'app ANY /export/{format} -> export.get',
            'system   -> error.not_found',
        ], array_slice($this->summaries(), 5));
    }

    public function testRestRecordsCarryNameAndVersion()
    {
        $route = (new RouteExtractor(__DIR__ . '/fixtures/repo'))->extract()[0];

        $this->assertSame('widget', $route['name']);
        $this->assertSame('v1', $route['version']);
    }

    public function testEveryRepositoryRestResourceGetsAnOptionsRoute()
    {
        $root = dirname(__DIR__, 3);
        $config = include $root . '/core/config/routes.php';
        $options = array_filter((new RouteExtractor($root))->extract(), function ($route) {
            return $route['method'] === 'OPTIONS';
        });

        $this->assertCount(count($config['routes']['rest']['v1']), $options);
    }
}
