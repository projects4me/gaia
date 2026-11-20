<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\Quality;

use PHPUnit\Framework\TestCase;

/**
 * The deprecations report fails the CI gate on Gaia code and only reports vendor code (agaia-4754).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DeprecationsGateTest extends TestCase
{
    private function runReport($line)
    {
        $log = tempnam(sys_get_temp_dir(), 'dep');
        file_put_contents($log, $line . "\n");
        $script = APP_PATH . '/tools/quality/deprecations-report.php';
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' --fail-on-gaia ' . escapeshellarg($log) . ' 2>&1', $out, $code);
        unlink($log);
        return $code;
    }

    public function testGaiaEntryFailsTheGate()
    {
        $this->assertSame(1, $this->runReport('PHP Deprecated:  Foo::bar(): Implicitly marking parameter $a as nullable is deprecated in /var/www/html/app/A.php on line 10'));
    }

    public function testVendorEntryDoesNotFailTheGate()
    {
        $this->assertSame(0, $this->runReport('PHP Deprecated:  X::count() is deprecated in /var/www/html/vendor/acme/x/X.php on line 7'));
    }

    public function testEmptyLogPasses()
    {
        $this->assertSame(0, $this->runReport(''));
    }
}
