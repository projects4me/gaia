<?php

namespace Gaia\Tests\Foundation\Mvc;

use PHPUnit\Framework\TestCase;
use Phalcon\Config;
use Gaia\MVC\Router;

/**
 * Tests that the router registers the configured controller and action for app routes.
 *
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RouterTest extends TestCase
{
    /**
     * @var mixed Global settings saved before the test.
     */
    private $originalSettings;

    protected function setUp(): void
    {
        global $settings;
        $this->originalSettings = $settings ?? null;
    }

    protected function tearDown(): void
    {
        global $settings;
        $settings = $this->originalSettings;
    }

    public function testAppRouteRegistersConfiguredControllerAndAction()
    {
        global $settings;
        $settings = new Config([
            'routes' => [
                'app' => [
                    'download' => [
                        'path' => '/download',
                        'controller' => 'download',
                        'action' => 'get',
                    ],
                ],
            ],
        ]);

        $router = new Router(false);
        $router->init();
        $router->handle('/download');

        $this->assertTrue($router->wasMatched());
        $this->assertSame('download', $router->getControllerName());
        $this->assertSame('get', $router->getActionName());
    }
}
