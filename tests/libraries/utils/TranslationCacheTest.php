<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\TranslationCache;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the per-locale translation cache files.
 */
class TranslationCacheTest extends TestCase
{
    /**
     * @var string
     */
    private $root;

    /**
     * @var string
     */
    private $directory;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gaia-translations-' . bin2hex(random_bytes(4));
        mkdir($this->root);
        $this->directory = $this->root . DIRECTORY_SEPARATOR . 'translations';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/{,*/}*', GLOB_BRACE) as $path) {
            is_file($path) && unlink($path);
        }
        is_dir($this->directory) && rmdir($this->directory);
        rmdir($this->root);
    }

    public function testNothingIsCachedAtFirst()
    {
        $this->assertNull((new TranslationCache($this->directory))->read('en-us'));
    }

    public function testWriteCreatesTheDirectoryAndReadReturnsTheDocument()
    {
        $cache = new TranslationCache($this->directory);

        $this->assertTrue($cache->write('en-us', '{"a":"A"}'));

        $this->assertSame('{"a":"A"}', file_get_contents($this->directory . '/en-us.json'));
        $this->assertSame('{"a":"A"}', $cache->read('en-us'));
        $this->assertSame([], glob($this->directory . '/*.tmp'));
    }

    public function testForgetDeletesOnlyThatLocale()
    {
        $cache = new TranslationCache($this->directory);
        $cache->write('en-us', '{}');
        $cache->write('ur-pk', '{}');

        $this->assertTrue($cache->forget('en-us'));

        $this->assertNull($cache->read('en-us'));
        $this->assertSame('{}', $cache->read('ur-pk'));
        $this->assertTrue($cache->forget('en-us'));
    }

    public function testForgetChangesTheVersion()
    {
        $cache = new TranslationCache($this->directory);
        $before = $cache->version('en-us');

        $cache->forget('en-us');
        $afterFirst = $cache->version('en-us');
        $cache->forget('en-us');

        $this->assertSame('', $before);
        $this->assertNotSame($before, $afterFirst);
        $this->assertNotSame($afterFirst, $cache->version('en-us'));
    }

    public function testForgetReportsFailureWhenTheVersionCannotChange()
    {
        $cache = new TranslationCache($this->directory);
        $cache->write('en-us', '{}');
        mkdir($this->directory . '/en-us.version');

        try {
            $this->assertFalse($cache->forget('en-us'));
            $this->assertNull($cache->read('en-us'));
            $this->assertSame([], glob($this->directory . '/*.tmp'));
        } finally {
            rmdir($this->directory . '/en-us.version');
        }
    }

    public function testADocumentBuiltBeforeAForgetIsNotWritten()
    {
        $cache = new TranslationCache($this->directory);
        $builtAt = $cache->version('en-us');
        $cache->forget('en-us');

        $this->assertFalse($cache->write('en-us', '{"stale":"yes"}', $builtAt));

        $this->assertNull($cache->read('en-us'));
        $this->assertSame([], glob($this->directory . '/*.tmp'));
        $this->assertTrue($cache->write('en-us', '{"fresh":"yes"}', $cache->version('en-us')));
    }

    /**
     * @dataProvider unsafeLocales
     */
    public function testLocalesThatAreNotPlainTagsAreRefused($locale)
    {
        $cache = new TranslationCache($this->directory);
        file_put_contents($this->root . '/secret.json', 'secret');

        foreach (['read', 'forget'] as $method) {
            try {
                $cache->$method($locale);
                $this->fail("{$method} accepted {$locale}");
            } catch (\InvalidArgumentException $e) {
                $this->assertFileExists($this->root . '/secret.json');
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        try {
            $cache->write($locale, 'x');
        } finally {
            $this->assertDirectoryDoesNotExist($this->directory);
        }
    }

    public function unsafeLocales()
    {
        return [
            'parent directory' => ['../secret'],
            'encoded parent' => ['..%2Fsecret'],
            'absolute' => ['/etc/passwd'],
            'nested' => ['en/us'],
            'backslash' => ['..\\secret'],
            'null byte' => ["en-us\0"],
            'upper case' => ['EN-US'],
            'empty' => [''],
            'not a string' => [null],
        ];
    }
}
