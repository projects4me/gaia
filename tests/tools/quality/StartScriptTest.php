<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\Quality;

use PHPUnit\Framework\TestCase;

/**
 * The container start script must not create files Apache cannot write (agaia-4772).
 *
 * The CLI creates logs/db.log on its first query; run as root it leaves the file unwritable for www-data and
 * every request then fails with a 500.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class StartScriptTest extends TestCase
{
    private function script()
    {
        return file_get_contents(__DIR__ . '/../../../docker/dev/start.sh');
    }

    public function testBackfillRunsAsWebServerUser()
    {
        $this->assertMatchesRegularExpression(
            '/su\s+-s\s+\/bin\/sh\s+www-data\s+-c\s+["\'][^"\']*php cli\.php search backfill/',
            $this->script()
        );
    }

    public function testNoCliStepRunsAsRoot()
    {
        $lines = preg_grep('/^\s*(if\s+)?php cli\.php/m', explode("\n", $this->script()));
        $this->assertSame([], array_values($lines), 'every php cli.php call must go through su www-data');
    }

    public function testLogsAreOwnedByWebServerBeforeApacheStarts()
    {
        $this->assertStringContainsString('chown -R www-data:www-data /var/www/html/logs', $this->script());
    }
}
