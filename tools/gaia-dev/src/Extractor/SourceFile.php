<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

use RuntimeException;

/**
 * Reads a repository source file (a PHP file that returns an array, or a JSON
 * file) into a plain array. The extractors share it so none of them parses files
 * on its own.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SourceFile
{
    /**
     * Include a PHP config file such as `core/config/routes.php` or a metadata
     * model, in its own scope, and return the array it returns.
     *
     * @param string $path
     * @return array
     */
    public static function readPhpArray(string $path): array
    {
        self::assertReadable($path);

        try {
            $result = (static function ($__path) {
                $config = [];
                $models = [];
                return include $__path;
            })($path);
        } catch (\Throwable $e) {
            throw new RuntimeException("{$path}: " . $e->getMessage(), 0, $e);
        }

        if (!is_array($result)) {
            throw new RuntimeException("{$path} does not return an array");
        }

        return self::normaliseLineEndings($result);
    }

    /**
     * Decode a JSON file into an associative array.
     *
     * @param string $path
     * @return array
     */
    public static function readJson(string $path): array
    {
        self::assertReadable($path);

        $result = json_decode(file_get_contents($path), true);
        if (!is_array($result)) {
            throw new RuntimeException("{$path} is not a JSON object or array: " . json_last_error_msg());
        }

        return self::normaliseLineEndings($result);
    }

    /**
     * Turn CRLF inside string values into LF, so a multi-line string in a source
     * file gives the same graph on a Windows checkout as on a Linux one.
     *
     * @param array $data
     * @return array
     */
    private static function normaliseLineEndings(array $data): array
    {
        array_walk_recursive($data, static function (&$value) {
            if (is_string($value)) {
                $value = str_replace("\r\n", "\n", $value);
            }
        });

        return $data;
    }

    /**
     * @param string $path
     */
    private static function assertReadable(string $path)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Source file not found: {$path}");
        }
    }
}
