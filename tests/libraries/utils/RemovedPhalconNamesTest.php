<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Fails when a Phalcon 4 class name that Gaia no longer has comes back.
 * Without the alias shim a reintroduced name is a fatal error at runtime.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests\Libraries\Utils
 * @category Test
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RemovedPhalconNamesTest extends TestCase
{
    /**
     * Whole class names only: Phalcon\Di\Di and Phalcon\Config\Config are fine.
     */
    private const PATTERN = '/Phalcon\\\\{1,2}(?:Di|DI|DiInterface|Config|Logger|Loader|Text|Exception)(?![\w\\\\])/';

    public function testNoRemovedPhalconClassNamesAreUsed()
    {
        $root = dirname(__DIR__, 3);
        $found = [];

        foreach ($this->files($root) as $file) {
            if ($file === __FILE__) {
                continue;
            }
            foreach (file($file) as $number => $line) {
                if (preg_match(self::PATTERN, $line, $match)) {
                    $found[] = substr($file, strlen($root) + 1) . ':' . ($number + 1) . ' ' . $match[0];
                }
            }
        }

        $this->assertSame([], $found, "Removed Phalcon 4 class names found:\n" . implode("\n", $found));
    }

    /**
     * @return string[]
     */
    private function files(string $root): array
    {
        $files = [];
        foreach (['app', 'core', 'tests', 'tools', 'public'] as $dir) {
            if (!is_dir("$root/$dir")) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $info) {
                if ($info->isFile() && $info->getExtension() === 'php') {
                    $files[] = $info->getPathname();
                }
            }
        }
        foreach (['cli.php', 'autoload.php'] as $name) {
            if (is_file("$root/$name")) {
                $files[] = "$root/$name";
            }
        }
        return $files;
    }
}
