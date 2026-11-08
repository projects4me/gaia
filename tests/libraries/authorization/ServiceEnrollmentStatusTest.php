<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Authorization;

use Gaia\Libraries\Authorization\ServiceEnrollmentStatus;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the enrollment state machine: which status may follow which,
 * and that a request past its expiry can only expire.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceEnrollmentStatusTest extends TestCase
{
    private const NOW = 1800000000;

    public function allowedTransitions()
    {
        return [
            ['pending', 'approved'],
            ['pending', 'denied'],
            ['pending', 'expired'],
            ['approved', 'delivered'],
            ['approved', 'expired'],
        ];
    }

    /**
     * @dataProvider allowedTransitions
     */
    public function testAllowedTransitionsBeforeExpiry($from, $to)
    {
        $this->assertNull(ServiceEnrollmentStatus::transitionError($from, $to, $this->later(60), self::NOW));
    }

    public function refusedTransitions()
    {
        return [
            ['pending', 'delivered'],
            ['approved', 'denied'],
            ['approved', 'pending'],
            ['denied', 'approved'],
            ['expired', 'approved'],
            ['delivered', 'approved'],
            ['delivered', 'delivered'],
        ];
    }

    /**
     * @dataProvider refusedTransitions
     */
    public function testRefusedTransitions($from, $to)
    {
        $this->assertNotNull(ServiceEnrollmentStatus::transitionError($from, $to, $this->later(60), self::NOW));
    }

    public function testAnUnknownStatusIsRefused()
    {
        $error = ServiceEnrollmentStatus::transitionError('pending', 'paused', $this->later(60), self::NOW);

        $this->assertStringStartsWith('status must be one of', $error);
    }

    public function testAnExpiredRequestCannotBeApprovedDeniedOrDelivered()
    {
        $expired = $this->later(0);

        foreach ([['pending', 'approved'], ['pending', 'denied'], ['approved', 'delivered']] as $change) {
            $this->assertSame(
                ServiceEnrollmentStatus::EXPIRED_ERROR,
                ServiceEnrollmentStatus::transitionError($change[0], $change[1], $expired, self::NOW),
                implode(' -> ', $change)
            );
        }
    }

    public function testAnExpiredRequestMayBeMarkedExpired()
    {
        $this->assertNull(ServiceEnrollmentStatus::transitionError('pending', 'expired', $this->later(-60), self::NOW));
    }

    public function testCurrentStatusExpiresOpenRequestsOnly()
    {
        $past = $this->later(-1);
        $future = $this->later(1);

        $this->assertSame('expired', ServiceEnrollmentStatus::current('pending', $past, self::NOW));
        $this->assertSame('expired', ServiceEnrollmentStatus::current('approved', $past, self::NOW));
        $this->assertSame('delivered', ServiceEnrollmentStatus::current('delivered', $past, self::NOW));
        $this->assertSame('denied', ServiceEnrollmentStatus::current('denied', $past, self::NOW));
        $this->assertSame('pending', ServiceEnrollmentStatus::current('pending', $future, self::NOW));
    }

    public function testAMissingOrBrokenExpiryCountsAsExpired()
    {
        $this->assertTrue(ServiceEnrollmentStatus::hasExpired(null, self::NOW));
        $this->assertTrue(ServiceEnrollmentStatus::hasExpired('not a date', self::NOW));
    }

    /**
     * @param  int $seconds
     * @return string
     */
    private function later($seconds)
    {
        return gmdate('Y-m-d H:i:s', self::NOW + $seconds);
    }
}
