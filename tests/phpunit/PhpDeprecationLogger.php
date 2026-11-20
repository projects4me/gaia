<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\PHPUnit;

use PHPUnit\Event\Test\PhpDeprecationTriggered;
use PHPUnit\Event\Test\PhpDeprecationTriggeredSubscriber;

/**
 * Logs every E_DEPRECATED a test triggers. PHPUnit's error handler takes these over, so PHP no longer logs
 * them on its own.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
final class PhpDeprecationLogger implements PhpDeprecationTriggeredSubscriber
{
    public function __construct(private readonly DiagnosticsLog $log)
    {
    }

    public function notify(PhpDeprecationTriggered $event): void
    {
        if ($event->wasSuppressed() || $event->ignoredByTest()) {
            return;
        }
        $this->log->deprecated($event->message(), $event->file(), $event->line());
    }
}
