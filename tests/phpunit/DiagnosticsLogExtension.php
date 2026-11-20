<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\PHPUnit;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Sends the deprecations tests trigger to the PHP diagnostics log (agaia-4758).
 *
 * Since PHPUnit 10 its error handler reports deprecations, notices and warnings as events instead of
 * handing them back to PHP, so they would never reach logs/php-diagnostics-phpunit.log. Warnings and
 * notices fail the run (failOnWarning, failOnNotice in phpunit.xml); deprecations only get logged here for
 * the PHP diagnostics gate (tools/quality/deprecations-report.php).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
final class DiagnosticsLogExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $log = new DiagnosticsLog();
        $facade->registerSubscribers(
            new PhpDeprecationLogger($log),
            new UserDeprecationLogger($log)
        );
    }
}
