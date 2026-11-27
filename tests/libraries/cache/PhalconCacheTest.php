<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Cache;

use Gaia\Libraries\Cache\NullCache;
use Gaia\Libraries\Cache\PhalconCache;
use Phalcon\Cache\Adapter\AdapterInterface;
use Phalcon\Cache\AdapterFactory;
use Phalcon\Cache\Cache;
use Phalcon\Logger\LoggerInterface;
use Phalcon\Storage\SerializerFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PhalconCache (on the memory adapter) and NullCache.
 */
class PhalconCacheTest extends TestCase
{
    /**
     * Builds a PhalconCache on a memory adapter.
     *
     * @param string               $version
     * @param LoggerInterface|null $logger
     * @return array [PhalconCache, the memory adapter]
     */
    private function memoryCache(string $version = '7', ?LoggerInterface $logger = null): array
    {
        $adapter = (new AdapterFactory(new SerializerFactory()))->newInstance(
            'memory',
            ['defaultSerializer' => 'php', 'lifetime' => 3600, 'prefix' => 'gaia-']
        );

        return [new PhalconCache(new Cache($adapter), 'memory', $version, $logger), $adapter];
    }

    public function testRoundTripsArraysAndScalars()
    {
        [$cache] = $this->memoryCache();

        $this->assertTrue($cache->set('meta.Agent', ['fields' => ['name' => 'varchar'], 'count' => 3]));
        $this->assertTrue($cache->set('answer', 42));

        $this->assertSame(['fields' => ['name' => 'varchar'], 'count' => 3], $cache->get('meta.Agent'));
        $this->assertSame(42, $cache->get('answer'));
    }

    public function testMissReturnsNull()
    {
        [$cache] = $this->memoryCache();

        $this->assertNull($cache->get('missing'));
    }

    public function testDeleteAndClearRemoveEntries()
    {
        [$cache] = $this->memoryCache();
        $cache->set('a', 1);
        $cache->set('b', 2);

        $this->assertTrue($cache->delete('a'));
        $this->assertNull($cache->get('a'));
        $this->assertSame(2, $cache->get('b'));

        $this->assertTrue($cache->clear());
        $this->assertNull($cache->get('b'));
    }

    public function testKeysCarryPrefixAndVersion()
    {
        [$cache, $adapter] = $this->memoryCache('2026.11-a');
        $cache->set('meta.Agent', 1);

        $this->assertSame(['gaia-2026.11-a.meta.Agent'], $adapter->getKeys());
    }

    public function testAnotherVersionDoesNotReadTheEntries()
    {
        [$old, $adapter] = $this->memoryCache('1');
        $old->set('meta.Agent', 'old');

        $new = new PhalconCache(new Cache($adapter), 'memory', '2');

        $this->assertNull($new->get('meta.Agent'));
        $this->assertSame('old', $old->get('meta.Agent'));
    }

    public function testKeysWithCharactersPhalconRefusesAreHashed()
    {
        [$cache, $adapter] = $this->memoryCache('1+build/3');
        $cache->set('meta:Agent/fields', 'value');

        $this->assertSame('value', $cache->get('meta:Agent/fields'));
        $this->assertSame(['gaia-1-build-3.h.' . sha1('meta:Agent/fields')], $adapter->getKeys());
    }

    public function testBackendFailuresFailOpenAndAreLogged()
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('get')->willThrowException(new \RuntimeException('down'));
        $adapter->method('has')->willThrowException(new \RuntimeException('down'));
        $adapter->method('set')->willThrowException(new \RuntimeException('down'));
        $adapter->method('delete')->willThrowException(new \RuntimeException('down'));
        $adapter->method('clear')->willThrowException(new \RuntimeException('down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(4))->method('warning')->with($this->stringContains('down'));

        $cache = new PhalconCache(new Cache($adapter), 'redis', '1', $logger);

        $this->assertNull($cache->get('key'));
        $this->assertFalse($cache->set('key', 1));
        $this->assertFalse($cache->delete('key'));
        $this->assertFalse($cache->clear());
    }

    public function testNullCacheAlwaysMisses()
    {
        $cache = new NullCache();

        $this->assertFalse($cache->set('key', 1));
        $this->assertNull($cache->get('key'));
        $this->assertTrue($cache->delete('key'));
        $this->assertTrue($cache->clear());
    }
}
