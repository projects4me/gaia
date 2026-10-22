<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\TranslationCache;
use Gaia\Libraries\Utils\TranslationCatalog;
use Gaia\Libraries\Utils\TranslationLocales;
use Gaia\Libraries\Utils\TranslationSource;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for serving translation documents from the cache or the database.
 */
class TranslationCatalogTest extends TestCase
{
    /**
     * @var string
     */
    private $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gaia-catalog-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        is_dir($this->directory) && rmdir($this->directory);
    }

    public function testListsTheSupportedLocales()
    {
        $this->assertSame(['en-us'], $this->catalog(new FakeTranslationSource([]))->locales());
    }

    public function testFirstRequestBuildsAndCachesTheDocumentAndTheSecondOnlyReadsTheCache()
    {
        $source = new FakeTranslationSource([
            'views.app.issue.lists.status.new' => 'New',
            'views.app.issue.lists.status.wont_fix' => "Won't Fix",
        ]);
        $catalog = $this->catalog($source);
        $expected = '{"views":{"app":{"issue":{"lists":{"status":{"new":"New","wont_fix":"Won\'t Fix"}}}}}}';

        $this->assertSame($expected, $catalog->document('en-us'));
        $this->assertSame($expected, file_get_contents($this->directory . '/en-us.json'));
        $this->assertSame(['en-us'], $source->requested);

        $this->assertSame($expected, $catalog->document('en-us'));
        $this->assertSame(['en-us'], $source->requested, 'the second request must not query the database');
    }

    public function testAForgottenCacheIsRebuiltWithTheNewValue()
    {
        $source = new FakeTranslationSource(['a.b' => 'Old']);
        $catalog = $this->catalog($source);
        $catalog->document('en-us');

        $source->labels = ['a.b' => 'New'];
        (new TranslationCache($this->directory))->forget('en-us');

        $this->assertSame('{"a":{"b":"New"}}', $catalog->document('en-us'));
        $this->assertCount(2, $source->requested);
    }

    public function testADocumentBuiltWhileARowChangesIsServedButNotCached()
    {
        $cache = new TranslationCache($this->directory);
        $source = new FakeTranslationSource(['a' => 'Old']);
        $source->whileReading = function () use ($cache) {
            $cache->forget('en-us');
        };

        $this->assertSame('{"a":"Old"}', $this->catalog($source)->document('en-us'));

        $this->assertNull($cache->read('en-us'));
    }

    public function testALocaleWithoutRowsIsAnEmptyObject()
    {
        $this->assertSame('{}', $this->catalog(new FakeTranslationSource([]))->document('en-us'));
    }

    public function testLabelsAreNotEscapedBeyondJson()
    {
        $document = $this->catalog(new FakeTranslationSource(['a' => 'Für / ü']))->document('en-us');

        $this->assertSame('{"a":"Für / ü"}', $document);
    }

    /**
     * @dataProvider unsupportedLocales
     */
    public function testUnsupportedLocalesReadAndWriteNothing($locale)
    {
        mkdir($this->directory);
        file_put_contents($this->directory . '/ur-pk.json', '{"cached":"yes"}');
        $source = new FakeTranslationSource(['a' => 'A']);

        $this->assertNull($this->catalog($source)->document($locale));

        $this->assertSame([], $source->requested);
        $this->assertSame(['ur-pk.json'], array_values(array_diff(scandir($this->directory), ['.', '..'])));
    }

    public function unsupportedLocales()
    {
        return [
            'not configured, though cached' => ['ur-pk'],
            'parent directory' => ['../en-us'],
            'encoded parent' => ['..%2Fen-us'],
            'path' => ['en-us/../../config'],
            'case' => ['EN-US'],
            'empty' => [''],
            'missing' => [null],
        ];
    }

    /**
     * @param  FakeTranslationSource $source
     * @return TranslationCatalog
     */
    private function catalog(FakeTranslationSource $source)
    {
        return new TranslationCatalog(new TranslationLocales(['en-us']), new TranslationCache($this->directory), $source);
    }
}

/**
 * Stands in for the translations table and records which locales were read.
 */
class FakeTranslationSource extends TranslationSource
{
    /**
     * @var array
     */
    public $labels;

    /**
     * @var string[]
     */
    public $requested = [];

    /**
     * Runs while the labels are read, e.g. a concurrent save.
     *
     * @var callable|null
     */
    public $whileReading;

    /**
     * @param array $labels
     */
    public function __construct(array $labels)
    {
        $this->labels = $labels;
    }

    /**
     * @param  string $locale
     * @return array
     */
    public function labelsFor($locale)
    {
        $this->requested[] = $locale;
        if ($this->whileReading) {
            ($this->whileReading)();
        }
        return $this->labels;
    }
}
