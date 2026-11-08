<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

/**
 * The codes of a service enrollment request: the short user code a service
 * shows and an admin matches (e.g. `KQ7-3MX`), and the poll token the service
 * reads its request with, which is stored only as a hash.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class EnrollmentCodes
{
    /**
     * Letters and digits of a user code, without the look-alikes 0, O, 1, I.
     */
    public const USER_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Characters on each side of the dash in a user code.
     */
    private const USER_CODE_HALF = 3;

    /**
     * Random bytes in a poll token (hex encoded).
     */
    private const POLL_TOKEN_BYTES = 32;

    /**
     * A new user code, `XXX-XXX`.
     *
     * @return string
     */
    public function userCode()
    {
        $last = strlen(self::USER_CODE_ALPHABET) - 1;
        $code = '';
        for ($i = 0; $i < 2 * self::USER_CODE_HALF; $i++) {
            if ($i === self::USER_CODE_HALF) {
                $code .= '-';
            }
            $code .= self::USER_CODE_ALPHABET[random_int(0, $last)];
        }

        return $code;
    }

    /**
     * A new poll token.
     *
     * @return string
     */
    public function pollToken()
    {
        return bin2hex(random_bytes(self::POLL_TOKEN_BYTES));
    }

    /**
     * The hash a poll token is stored as.
     *
     * @param  string $pollToken
     * @return string
     */
    public static function hash($pollToken)
    {
        return hash('sha256', (string) $pollToken);
    }

    /**
     * Whether a poll token matches a stored hash (in constant time).
     *
     * @param  string|null $storedHash
     * @param  mixed       $pollToken
     * @return bool
     */
    public static function matches($storedHash, $pollToken)
    {
        if (!is_string($pollToken) || $pollToken === '' || !is_string($storedHash) || $storedHash === '') {
            return false;
        }

        return hash_equals($storedHash, self::hash($pollToken));
    }
}
