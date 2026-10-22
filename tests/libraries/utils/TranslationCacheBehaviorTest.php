<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\TranslationCache;
use Gaia\MVC\Models\Behaviors\translationCacheBehavior;
use Phalcon\Mvc\ModelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for dropping a locale's cached translations when its rows change.
 */
class TranslationCacheBehaviorTest extends TestCase
{
    /**
     * @var string
     */
    private $directory;

    /**
     * @var TranslationCache
     */
    private $cache;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gaia-behavior-' . bin2hex(random_bytes(4));
        $this->cache = new TranslationCache($this->directory);
        $this->cache->write('en-us', '{"a":"A"}');
        $this->cache->write('ur-pk', '{"a":"A"}');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    /**
     * Soft deletes arrive as afterUpdate.
     *
     * @dataProvider changeEvents
     */
    public function testAChangedRowDropsTheCacheOfItsLocale($event)
    {
        (new translationCacheBehavior($this->cache))->notify($event, $this->model('en-us', ['locale' => 'en-us']));

        $this->assertNull($this->cache->read('en-us'));
        $this->assertNotNull($this->cache->read('ur-pk'));
    }

    public function changeEvents()
    {
        return [['afterCreate'], ['afterUpdate'], ['afterDelete']];
    }

    public function testARowMovedToAnotherLocaleDropsBothCaches()
    {
        (new translationCacheBehavior($this->cache))->notify('afterUpdate', $this->model('en-us', ['locale' => 'ur-pk']));

        $this->assertNull($this->cache->read('en-us'));
        $this->assertNull($this->cache->read('ur-pk'));
    }

    public function testOtherEventsKeepTheCache()
    {
        (new translationCacheBehavior($this->cache))->notify('beforeValidation', $this->model('en-us', []));

        $this->assertNotNull($this->cache->read('en-us'));
    }

    public function testAnInvalidLocaleIsIgnored()
    {
        (new translationCacheBehavior($this->cache))->notify('afterCreate', $this->model('../en-us', []));

        $this->assertNotNull($this->cache->read('en-us'));
    }

    /**
     * @param  string $locale
     * @param  array  $snapshot
     * @return ModelInterface
     */
    private function model($locale, array $snapshot)
    {
        $model = $this->getMockBuilder(ModelInterface::class)
            ->addMethods(['hasSnapshotData', 'getSnapshotData'])
            ->getMockForAbstractClass();
        $model->method('hasSnapshotData')->willReturn(!empty($snapshot));
        $model->method('getSnapshotData')->willReturn($snapshot);
        $model->locale = $locale;
        return $model;
    }
}
