<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

/**
 * The state machine of a service enrollment request: its statuses, which
 * status may follow which, and when a request has run out of time.
 *
 *     pending  -> approved | denied | expired
 *     approved -> delivered | expired
 *
 * Denied, expired and delivered are final. A pending or approved request
 * whose `expiresAt` has passed counts as expired, so it can no longer be
 * approved, denied or delivered. See rbac decision D-066.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceEnrollmentStatus
{
    /**
     * Waiting for an admin.
     */
    public const PENDING = 'pending';

    /**
     * An admin approved it; the service gets its secret on its next poll.
     */
    public const APPROVED = 'approved';

    /**
     * An admin turned it down.
     */
    public const DENIED = 'denied';

    /**
     * It ran out of time before it was decided or delivered.
     */
    public const EXPIRED = 'expired';

    /**
     * The service received its client id and secret.
     */
    public const DELIVERED = 'delivered';

    /**
     * Every status a request may have.
     */
    public const STATUSES = [self::PENDING, self::APPROVED, self::DENIED, self::EXPIRED, self::DELIVERED];

    /**
     * The statuses each status may change to.
     */
    private const TRANSITIONS = [
        self::PENDING => [self::APPROVED, self::DENIED, self::EXPIRED],
        self::APPROVED => [self::DELIVERED, self::EXPIRED],
    ];

    /**
     * The statuses that are still waiting for something and so run out of time.
     */
    private const OPEN = [self::PENDING, self::APPROVED];

    /**
     * Error for a decision on a request that ran out of time.
     */
    public const EXPIRED_ERROR = 'The enrollment request has expired and can no longer be approved, denied or delivered.';

    /**
     * Whether the expiry time has passed.
     *
     * @param  string|null $expiresAt UTC, `Y-m-d H:i:s`
     * @param  int         $now       Unix time
     * @return bool
     */
    public static function hasExpired($expiresAt, $now)
    {
        $expires = strtotime((string) $expiresAt . ' UTC');
        return $expires === false || $expires <= (int) $now;
    }

    /**
     * The status a request has now: a pending or approved request past its
     * expiry is expired.
     *
     * @param  string      $status    The stored status
     * @param  string|null $expiresAt UTC, `Y-m-d H:i:s`
     * @param  int         $now       Unix time
     * @return string
     */
    public static function current($status, $expiresAt, $now)
    {
        $status = (string) $status;
        if (in_array($status, self::OPEN, true) && self::hasExpired($expiresAt, $now)) {
            return self::EXPIRED;
        }

        return $status;
    }

    /**
     * Why a request may not change from one status to another, or null when
     * it may. A request past its expiry may only become expired.
     *
     * @param  string      $from      The stored status
     * @param  string      $to        The requested status
     * @param  string|null $expiresAt UTC, `Y-m-d H:i:s`
     * @param  int         $now       Unix time
     * @return string|null
     */
    public static function transitionError($from, $to, $expiresAt, $now)
    {
        $from = (string) $from;
        $to = (string) $to;

        if (!in_array($to, self::STATUSES, true)) {
            return 'status must be one of: ' . implode(', ', self::STATUSES);
        }

        $allowed = isset(self::TRANSITIONS[$from]) ? self::TRANSITIONS[$from] : [];
        if (!in_array($to, $allowed, true)) {
            return 'An enrollment request cannot change from ' . ($from === '' ? 'no status' : $from) . ' to ' . $to . '.';
        }

        if ($to !== self::EXPIRED && self::hasExpired($expiresAt, $now)) {
            return self::EXPIRED_ERROR;
        }

        return null;
    }
}
