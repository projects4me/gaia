<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Cache;

use Gaia\Libraries\Cache\CacheFactory;
use Gaia\Libraries\Cache\NullCache;
use Gaia\Libraries\Cache\PhalconCache;
use Phalcon\Config\Config;
use Phalcon\Logger\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CacheFactory: the adapter comes from the settings, and an
 * unknown or unusable adapter falls back to NullCache with one warning.
 */
class CacheFactoryTest extends TestCase
{
    /**
     * @var string
     */
    private $streamDir;

    protected function setUp(): void
    {
        $this->streamDir = sys_get_temp_dir() . '/gaia-cache-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->streamDir);
    }

    public function testNoAdapterGivesNullCache()
    {
        $this->assertInstanceOf(NullCache::class, (new CacheFactory([]))->create());
        $this->assertInstanceOf(NullCache::class, (new CacheFactory(['adapter' => 'none']))->create());
    }

    public function testFromSettingsAcceptsConfigArrayOrNothing()
    {
        $fromConfig = CacheFactory::fromSettings(new Config(['adapter' => 'memory']));
        $this->assertInstanceOf(PhalconCache::class, $fromConfig);
        $this->assertTrue($fromConfig->set('k', 'v'));
        $this->assertSame('v', $fromConfig->get('k'));

        $this->assertInstanceOf(PhalconCache::class, CacheFactory::fromSettings(['adapter' => 'memory']));
        $this->assertInstanceOf(NullCache::class, CacheFactory::fromSettings(null));
    }

    public function testMemoryAdapterGivesPhalconCache()
    {
        $cache = (new CacheFactory(['adapter' => 'memory', 'prefix' => 'p-', 'version' => '1']))->create();

        $this->assertInstanceOf(PhalconCache::class, $cache);
        $this->assertSame('memory', $cache->adapterName());
    }

    public function testStreamAdapterCreatesItsDirectoryAndRoundTrips()
    {
        $factory = new CacheFactory(['adapter' => 'stream', 'version' => '1', 'streamDir' => $this->streamDir]);
        $cache = $factory->create();

        $this->assertInstanceOf(PhalconCache::class, $cache);
        $this->assertSame('stream', $cache->adapterName());
        $this->assertDirectoryExists($this->streamDir);

        $this->assertTrue($cache->set('models', ['Agent' => ['name' => 'varchar']]));
        $this->assertSame(['Agent' => ['name' => 'varchar']], $factory->create()->get('models'));

        $this->assertTrue($cache->clear());
        $this->assertNull($factory->create()->get('models'));
    }

    public function testAdapterNameIsCaseInsensitive()
    {
        $cache = (new CacheFactory(['adapter' => 'Memory']))->create();

        $this->assertInstanceOf(PhalconCache::class, $cache);
    }

    public function testUnknownAdapterFallsBackToNullCacheWithOneWarning()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('"memcached"'));

        $this->assertInstanceOf(NullCache::class, (new CacheFactory(['adapter' => 'memcached'], $logger))->create());
    }

    public function testApcuWithoutTheExtensionFallsBackToNullCache()
    {
        if (extension_loaded('apcu') && function_exists('apcu_enabled') && apcu_enabled()) {
            $this->assertInstanceOf(PhalconCache::class, (new CacheFactory(['adapter' => 'apcu']))->create());
            return;
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with($this->stringContains('APCu'));

        $this->assertInstanceOf(NullCache::class, (new CacheFactory(['adapter' => 'apcu'], $logger))->create());
    }

    public function testUnreachableRedisFallsBackToNullCache()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $cache = (new CacheFactory(
            ['adapter' => 'redis', 'redis' => ['host' => '127.0.0.1', 'port' => 1, 'index' => 0]],
            $logger
        ))->create();

        $this->assertInstanceOf(NullCache::class, $cache);
    }

    public function testUnwritableStreamDirectoryFallsBackToNullCache()
    {
        // A file where the directory should be: it can be neither created nor written.
        file_put_contents($this->streamDir, 'not a directory');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $cache = (new CacheFactory(['adapter' => 'stream', 'streamDir' => $this->streamDir], $logger))->create();

        $this->assertInstanceOf(NullCache::class, $cache);
    }

    /**
     * Removes a directory (or file) and everything under it.
     *
     * @param string $path
     */
    private function removeDirectory(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
