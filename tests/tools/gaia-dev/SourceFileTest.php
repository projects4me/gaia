<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\SourceFile;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the shared source file reader (agaia-4128).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SourceFileTest extends TestCase
{
    /**
     * @var string
     */
    private $path;

    protected function tearDown(): void
    {
        if ($this->path !== null && is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testPhpStringsWithCrlfComeBackWithLf()
    {
        $this->path = tempnam(sys_get_temp_dir(), 'gaia-dev');
        file_put_contents($this->path, "<?php\r\nreturn ['a' => ['b' => \"one\r\ntwo\"]];\r\n");

        $this->assertSame(['a' => ['b' => "one\ntwo"]], SourceFile::readPhpArray($this->path));
    }

    public function testJsonStringsWithCrlfComeBackWithLf()
    {
        $this->path = tempnam(sys_get_temp_dir(), 'gaia-dev');
        file_put_contents($this->path, "{\r\n  \"a\": [\"one\\r\\ntwo\", 3]\r\n}\r\n");

        $this->assertSame(['a' => ["one\ntwo", 3]], SourceFile::readJson($this->path));
    }
}
