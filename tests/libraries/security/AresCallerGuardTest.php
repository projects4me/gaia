<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Exception\UnAuthorized;
use Gaia\Libraries\Security\AresCallerGuard;

/**
 * Tests that only a caller with the configured Ares secret gets through, and
 * that no caller does when no secret is configured.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AresCallerGuardTest extends TestCase
{
    public function testAcceptsTheConfiguredSecret(): void
    {
        (new AresCallerGuard('ares-unit-secret'))->assertCaller('ares-unit-secret');

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider refusedSecrets
     * @param mixed $presented
     */
    public function testRefusesAnyOtherSecret($presented): void
    {
        $this->expectException(UnAuthorized::class);

        (new AresCallerGuard('ares-unit-secret'))->assertCaller($presented);
    }

    public function refusedSecrets(): array
    {
        return [
            'missing' => [null],
            'empty' => [''],
            'wrong' => ['ares-unit-secret-2'],
            'prefix' => ['ares-unit'],
            'not a string' => [['ares-unit-secret']],
        ];
    }

    public function testRefusesEveryoneWhenNoSecretIsConfigured(): void
    {
        $this->expectException(UnAuthorized::class);

        (new AresCallerGuard(''))->assertCaller('');
    }
}
