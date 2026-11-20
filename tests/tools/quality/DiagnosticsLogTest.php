<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\Quality;

use Gaia\Tests\PHPUnit\DiagnosticsLog;
use PHPUnit\Framework\TestCase;
use Quality\DeprecationGrouper;

/**
 * Deprecations tests trigger reach the PHP diagnostics log in a format the gate reads (agaia-4758).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DiagnosticsLogTest extends TestCase
{
    public function testDeprecationLineMatchesThePhpLogFormatTheGateReads()
    {
        $line = DiagnosticsLog::deprecationLine('Foo::bar(): Passing null is deprecated', '/var/www/html/app/A.php', 12);

        $this->assertSame('PHP Deprecated:  Foo::bar(): Passing null is deprecated in /var/www/html/app/A.php on line 12', $line);
        $this->assertMatchesRegularExpression(DeprecationGrouper::LINE, $line);
    }

    public function testDeprecatedAppendsToTheErrorLog()
    {
        $log = tempnam(sys_get_temp_dir(), 'diag');
        $previous = ini_set('error_log', $log);
        try {
            (new DiagnosticsLog())->deprecated('X is deprecated', '/var/www/html/vendor/acme/X.php', 7);
        } finally {
            ini_set('error_log', $previous);
        }
        $content = file_get_contents($log);
        unlink($log);

        $this->assertStringContainsString('PHP Deprecated:  X is deprecated in /var/www/html/vendor/acme/X.php on line 7', $content);
    }
}
