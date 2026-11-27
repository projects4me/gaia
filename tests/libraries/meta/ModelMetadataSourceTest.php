<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Meta;

use Gaia\Libraries\Cache\CacheInterface;
use Gaia\Libraries\Cache\NullCache;
use Gaia\Libraries\Meta\CachedModelMetadataSource;
use Gaia\Libraries\Meta\FileModelMetadataSource;
use Gaia\Libraries\Meta\Manager;
use Gaia\Libraries\Meta\ModelMetadataSource;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the model metadata sources and how the Manager picks one.
 */
class ModelMetadataSourceTest extends TestCase
{
    private function di(): \Phalcon\Di\FactoryDefault
    {
        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('fileHandler', new class {
            public function readFile($path)
            {
                $model = basename($path, '.php');
                return array($model => array(
                    'tableName' => 'agaia4970s',
                    'fields' => array(
                        'id' => array('type' => 'varchar', 'length' => 36, 'primary' => true, 'null' => false),
                        'name' => array('type' => 'varchar', 'length' => 50, 'null' => false),
                    ),
                ));
            }
        });
        $di->set('migrationHandler', new \Gaia\Db\Migration\Postgres());

        return $di;
    }

    private function memoryCache(): CacheInterface
    {
        return new class implements CacheInterface {
            public $store = array();
            public $sets = 0;

            public function get(string $key)
            {
                return $this->store[$key] ?? null;
            }

            public function set(string $key, $value, ?int $ttl = null): bool
            {
                $this->sets++;
                $this->store[$key] = $value;
                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->store[$key]);
                return true;
            }

            public function clear(): bool
            {
                $this->store = array();
                return true;
            }
        };
    }

    private function countingSource(array $metadata): ModelMetadataSource
    {
        return new class ($metadata) implements ModelMetadataSource {
            public $calls = 0;
            private $metadata;

            public function __construct($metadata)
            {
                $this->metadata = $metadata;
            }

            public function get(string $model): array
            {
                $this->calls++;
                return $this->metadata;
            }
        };
    }

    public function testFileSourceBuildsTheArrayTheManagerServes()
    {
        $di = $this->di();
        $manager = new Manager($di);
        $fromSource = (new FileModelMetadataSource($di, $manager))->get('Agaia4970File');

        $this->assertSame('agaia4970s', $fromSource['tableName']);
        $this->assertSame(array('id', 'name'), $fromSource[\Phalcon\Mvc\Model\MetaData::MODELS_ATTRIBUTES]);
        $this->assertSame($fromSource, $manager->getModelMeta('Agaia4970File'));
    }

    public function testCachedSourceBuildsOnceAndThenReadsTheCache()
    {
        $cache = $this->memoryCache();
        $inner = $this->countingSource(array('tableName' => 'x'));
        $reads = array();
        $source = new CachedModelMetadataSource($inner, $cache, function ($seconds, $hit) use (&$reads) {
            $reads[] = $hit;
        });

        $this->assertSame(array('tableName' => 'x'), $source->get('Foo'));
        $this->assertSame(array('tableName' => 'x'), $source->get('Foo'));
        $this->assertSame(array(false, true), $reads);
        $this->assertSame(1, $inner->calls);
        $this->assertArrayHasKey('meta.model.Foo', $cache->store);
    }

    public function testManagerUsesTheCachedSourceWhenACacheIsConfigured()
    {
        $di = $this->di();
        $cache = $this->memoryCache();
        $di->setShared('cache', $cache);

        (new Manager($di))->getModelMeta('Agaia4970Cached');

        $this->assertArrayHasKey('meta.model.Agaia4970Cached', $cache->store);
    }

    public function testManagerSkipsTheCacheForNullCache()
    {
        $di = $this->di();
        $di->setShared('cache', new NullCache());
        $manager = new class ($di) extends Manager {
            public function source()
            {
                return $this->getSource();
            }
        };

        $this->assertInstanceOf(FileModelMetadataSource::class, $manager->source());
    }

    public function testManagerReadsOnlyThroughItsSource()
    {
        $inner = $this->countingSource(array('tableName' => 'y'));
        $manager = new Manager($this->di(), $inner);

        $manager->getModelMeta('Agaia4970Injected');
        $manager->getModelMeta('Agaia4970Injected');

        $this->assertSame(1, $inner->calls);
    }
}
