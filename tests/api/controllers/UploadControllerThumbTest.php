<?php

namespace Gaia\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\REST\Controllers\UploadController;

/**
 * Tests the thumbnail step of POST /api/v1/upload: a thumbnail is written for images, without warnings,
 * and an image that cannot be thumbnailed yields no thumbnail instead of an error.
 *
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class UploadControllerThumbTest extends TestCase
{
    private $dir;

    protected function setUp(): void
    {
        global $logger;
        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }
        $logger = new \Phalcon\Logger\Logger('test', ['main' => new \Phalcon\Logger\Adapter\Noop()]);
        $this->dir = sys_get_temp_dir() . DS . 'thumbtest' . bin2hex(random_bytes(4));
        mkdir($this->dir . DS . 'thumbnail', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DS . 'thumbnail' . DS . '*') as $f) {
            @unlink($f);
        }
        foreach (glob($this->dir . DS . '*') as $f) {
            is_file($f) && @unlink($f);
        }
        @rmdir($this->dir . DS . 'thumbnail');
        @rmdir($this->dir);
    }

    private function controller()
    {
        $ref = new \ReflectionClass(ThumbUploadController::class);
        return $ref->newInstanceWithoutConstructor();
    }

    private function image(string $type, int $w, int $h): string
    {
        $im = new \Imagick();
        $im->newImage($w, $h, new \ImagickPixel('red'));
        $im->setImageFormat($type);
        $file = $this->dir . DS . $type . $w . 'x' . $h;
        $im->writeImage($type . ':' . $file);
        return $file;
    }

    public static function sizes(): array
    {
        return [
            'png wide' => ['png', 500, 200],
            'jpeg tall' => ['jpeg', 200, 500],
            'gif odd ratio' => ['gif', 333, 217],
            'png exact 1.5' => ['png', 300, 200],
        ];
    }

    /**
     * @dataProvider sizes
     */
    public function testThumbnailIsWrittenWithoutWarnings(string $type, int $w, int $h): void
    {
        $file = $this->image($type, $w, $h);
        $warnings = [];
        set_error_handler(function ($no, $str) use (&$warnings) {
            $warnings[] = $str;
            return true;
        });
        try {
            $ok = $this->controller()->generateThumbForTest($file, $this->dir, 'abc');
        } finally {
            restore_error_handler();
        }
        $this->assertTrue($ok);
        $this->assertFileExists($this->dir . DS . 'thumbnail' . DS . 'thumb_abc.jpg');
        $this->assertSame([], $warnings);
    }

    public function testUnreadableImageGivesNoThumbnailAndNoError(): void
    {
        $file = $this->dir . DS . 'broken';
        file_put_contents($file, 'not an image');
        $this->assertFalse($this->controller()->generateThumbForTest($file, $this->dir, 'bad'));
    }
}

/**
 * Exposes the protected thumbnail step for the test.
 */
class ThumbUploadController extends UploadController
{
    public function generateThumbForTest(string $file, string $path, string $id): bool
    {
        return $this->_generateThumb($file, $path, $id);
    }
}
