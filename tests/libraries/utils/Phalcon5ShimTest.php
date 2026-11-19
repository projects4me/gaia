<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use PHPUnit\Framework\TestCase;

/**
 * The Phalcon 4 names Gaia uses work on both Phalcon 4 and Phalcon 5
 * (core/libs/compat/phalcon5.php).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests\Libraries\Utils
 * @category Test
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Phalcon5ShimTest extends TestCase
{
    public function testShimLoadsTwiceWithoutChangingAnything()
    {
        $loader = \Phalcon\Loader::class;

        require APP_PATH . '/core/libs/compat/phalcon5.php';

        $this->assertTrue(class_exists($loader, false));
    }

    /**
     * Phalcon 4 keeps its own classes; Phalcon 5 gets the shim's, which keep
     * the Phalcon 4 API.
     */
    public function testNamesComeFromTheRightPlace()
    {
        $phalcon5 = class_exists('Phalcon\Autoload\Loader');

        foreach (['Phalcon\Exception', 'Phalcon\Loader', 'Phalcon\Text'] as $name) {
            $this->assertSame(!$phalcon5, (new \ReflectionClass($name))->isInternal(), $name);
        }

        $this->assertSame(
            $phalcon5 ? 'Phalcon\Autoload\Loader' : false,
            get_parent_class(new \Phalcon\Loader())
        );
    }

    public function testDiAndDiInterface()
    {
        $di = new \Phalcon\Di();

        $this->assertInstanceOf(\Phalcon\Di\DiInterface::class, $di);
        // Phalcon 4 names it Phalcon\Di\DiInterface; Gaia uses the old name only in docblocks.
        $this->assertSame(
            class_exists('Phalcon\Autoload\Loader'),
            interface_exists('Phalcon\DiInterface') && $di instanceof \Phalcon\DiInterface
        );
    }

    public function testConfig()
    {
        $config = new \Phalcon\Config(['oauth' => ['failedLoginAttemptsLimit' => 5]]);

        $this->assertSame(5, $config->path('oauth.failedLoginAttemptsLimit'));
        $this->assertSame(['oauth' => ['failedLoginAttemptsLimit' => 5]], $config->toArray());
    }

    public function testLogger()
    {
        $logger = new \Phalcon\Logger('test', ['main' => new \Phalcon\Logger\Adapter\Noop()]);
        $logger->setLogLevel(\Phalcon\Logger::DEBUG);

        $this->assertSame(\Phalcon\Logger::DEBUG, $logger->getLogLevel());
    }

    public function testExceptionIsAnException()
    {
        $exception = new \Phalcon\Exception('Id must be set');

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertSame('Id must be set', $exception->getMessage());
    }

    public function testLoaderRegistersNamespacesClassesAndDirectories()
    {
        $loader = new \Phalcon\Loader();

        $this->assertSame($loader, $loader->registerNamespaces(['Gaia\Tests\Compat' => APP_PATH . '/tests/libraries/utils/']));
        $this->assertSame($loader, $loader->registerClasses(['Gaia\Tests\Compat\Thing' => APP_PATH . '/thing.php']));
        $this->assertSame($loader, $loader->registerDirs([APP_PATH . '/core/tasks']));

        // Phalcon 5 keys namespaces with a trailing separator.
        $namespaces = array_map(function ($namespace) {
            return rtrim($namespace, '\\');
        }, array_keys($loader->getNamespaces()));
        $this->assertContains('Gaia\Tests\Compat', $namespaces);
        $this->assertArrayHasKey('Gaia\Tests\Compat\Thing', $loader->getClasses());
    }

    public function testText()
    {
        $this->assertSame('IssueActivity', \Phalcon\Text::camelize('issue_activity'));
        $this->assertSame('in progress', \Phalcon\Text::humanize('in_progress'));
        $this->assertSame('hasmany', \Phalcon\Text::lower('hasMany'));

        $random = \Phalcon\Text::random(\Phalcon\Text::RANDOM_ALNUM, 36);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{36}$/', $random);
    }
}
