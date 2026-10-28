<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Authorization;

use Gaia\Libraries\Authorization\SessionExpiry;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the per-session expiry policy.
 */
class SessionExpiryTest extends TestCase
{
    private const NOW = 1798761600; // 2027-01-01 00:00:00 UTC

    private function policy()
    {
        return new SessionExpiry(['sessionTimeout' => '1', 'sessionTimeoutForRememberMe' => '14']);
    }

    public function testSessionWithoutRememberMeEndsAfterSessionTimeout()
    {
        $this->assertSame('2027-01-02 00:00:00', $this->policy()->expiresAt(false, self::NOW));
    }

    public function testRememberMeSessionEndsAfterRememberMeTimeout()
    {
        $this->assertSame('2027-01-15 00:00:00', $this->policy()->expiresAt(true, self::NOW));
    }

    public function testNonNumericTimeoutIsRejected()
    {
        $policy = new SessionExpiry(['sessionTimeout' => 'a day', 'sessionTimeoutForRememberMe' => '14']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Session Timeout should be a number');
        $policy->expiresAt(false, self::NOW);
    }

    public function testWindowIsOpenUntilItsEnd()
    {
        $this->assertTrue(SessionExpiry::isOpen('2027-01-01 00:00:01', self::NOW));
        $this->assertFalse(SessionExpiry::isOpen('2027-01-01 00:00:00', self::NOW));
        $this->assertFalse(SessionExpiry::isOpen('2026-12-31 23:59:59', self::NOW));
    }

    public function testMissingExpiryIsClosed()
    {
        $this->assertFalse(SessionExpiry::isOpen(null, self::NOW));
        $this->assertFalse(SessionExpiry::isOpen('', self::NOW));
    }
}
