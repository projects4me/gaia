<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\PHPUnit;

/**
 * Writes a deprecation to the PHP error log (error_log, set in TestHelper.php) in PHP's own format, so
 * tools/quality/deprecations-report.php reads it like any other PHP log entry.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DiagnosticsLog
{
    /**
     * The log line PHP itself writes for an E_DEPRECATED or E_USER_DEPRECATED, without the date prefix.
     *
     * @param string $message
     * @param string $file
     * @param int $line
     * @return string
     */
    public static function deprecationLine(string $message, string $file, int $line): string
    {
        return "PHP Deprecated:  {$message} in {$file} on line {$line}";
    }

    /**
     * Appends the deprecation to the PHP error log.
     *
     * @param string $message
     * @param string $file
     * @param int $line
     * @return void
     */
    public function deprecated(string $message, string $file, int $line): void
    {
        error_log(self::deprecationLine($message, $file, $line));
    }
}
