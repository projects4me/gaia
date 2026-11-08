<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

/**
 * How many service enrollment requests one IP address may make: at most
 * `requestsPerIp` in the last `windowMinutes` (`serviceClients.enrollment` in
 * `config/serviceclients.php`). The requests themselves are the record, so
 * there is no separate counter to keep.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class EnrollmentRequestLimit
{
    /**
     * Error for an address that has made too many requests.
     */
    public const LIMIT_ERROR = 'Too many enrollment requests from this address. Try again later.';

    /**
     * Requests per address when the configuration does not say.
     */
    public const DEFAULT_REQUESTS_PER_IP = 5;

    /**
     * Minutes in a window when the configuration does not say.
     */
    public const DEFAULT_WINDOW_MINUTES = 15;

    /**
     * @var int
     */
    private $requestsPerIp;

    /**
     * @var int
     */
    private $windowMinutes;

    /**
     * @param int $requestsPerIp
     * @param int $windowMinutes
     */
    public function __construct($requestsPerIp, $windowMinutes)
    {
        $this->requestsPerIp = max(1, (int) $requestsPerIp);
        $this->windowMinutes = max(1, (int) $windowMinutes);
    }

    /**
     * Refuse the request when the address has used up its window.
     *
     * @param  string $ip
     * @param  int    $now Unix time
     * @return void
     * @throws \Gaia\Exception\TooManyRequests
     */
    public function assertAllowed($ip, $now)
    {
        $since = gmdate('Y-m-d H:i:s', (int) $now - 60 * $this->windowMinutes);
        if ($this->requestsSince((string) $ip, $since) >= $this->requestsPerIp) {
            throw new \Gaia\Exception\TooManyRequests(self::LIMIT_ERROR, 60 * $this->windowMinutes);
        }
    }

    /**
     * Requests the address made since the given time (deleted ones included,
     * so deleting a request does not give the address another try).
     *
     * @param  string $ip
     * @param  string $since UTC, `Y-m-d H:i:s`
     * @return int
     */
    protected function requestsSince($ip, $since)
    {
        $row = \Phalcon\Di::getDefault()->getShared('db')->fetchOne(
            'SELECT COUNT(*) AS requests FROM serviceenrollments WHERE "requesterIp" = ? AND "dateCreated" > ?',
            \Phalcon\Db\Enum::FETCH_ASSOC,
            [$ip, $since]
        );

        return isset($row['requests']) ? (int) $row['requests'] : 0;
    }
}
