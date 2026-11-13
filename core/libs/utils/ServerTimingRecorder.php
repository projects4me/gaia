<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * Records request, boot and database timings into a ServerTiming collector and sends them as response headers.
 *
 * @class ServerTimingRecorder
 * @package Gaia\Libraries\Utils
 * @author Hammad Hassan <gollomer@gmail.com>
 * @license AGPLv3
 */
class ServerTimingRecorder
{
    const SCHEMA = 'schema';
    const DB = 'db';

    /**
     * @var ServerTiming
     */
    private $timing;

    /**
     * @var float request start time
     */
    private $requestStart;

    /**
     * @var string value of Timing-Allow-Origin, empty to not send it
     */
    private $allowOrigin;

    /**
     * @var float|null start of the query in flight
     */
    private $queryStart = null;

    /**
     * @param float $requestStart microtime(true) at the start of the request
     * @param string $allowOrigin Timing-Allow-Origin value, empty to not send it
     */
    public function __construct($requestStart, $allowOrigin = '')
    {
        $this->timing = new ServerTiming();
        $this->requestStart = $requestStart;
        $this->allowOrigin = $allowOrigin;
    }

    /**
     * Sends Server-Timing (and Timing-Allow-Origin) with whatever response is produced.
     */
    public function registerHeaders()
    {
        header_register_callback(function () {
            foreach ($this->headers() as $header) {
                header($header);
            }
        });
    }

    /**
     * @return string[] header lines for the response
     */
    public function headers()
    {
        $this->timing->set('total', microtime(true) - $this->requestStart);
        $headers = ['Server-Timing: ' . $this->timing->format([self::DB => 'queries', self::SCHEMA => 'queries'])];
        if ($this->allowOrigin !== '') {
            $headers[] = 'Timing-Allow-Origin: ' . $this->allowOrigin;
        }
        return $headers;
    }

    /**
     * Marks the end of bootstrap, before the request is handled. Schema and db are always reported.
     */
    public function markBooted()
    {
        $this->timing->set('boot', microtime(true) - $this->requestStart);
        $this->timing->ensure(self::SCHEMA);
        $this->timing->ensure(self::DB);
    }

    /**
     * Call when a query is about to run.
     */
    public function beforeQuery()
    {
        $this->queryStart = microtime(true);
    }

    /**
     * Call when a query finished. A call without a matching beforeQuery is ignored.
     *
     * @param string $sql the statement that ran
     */
    public function afterQuery($sql)
    {
        if ($this->queryStart === null) {
            return;
        }
        $this->timing->add(self::classify($sql), microtime(true) - $this->queryStart);
        $this->queryStart = null;
    }

    /**
     * Statements that read the catalog (information_schema, pg_catalog tables) are schema, the rest db.
     *
     * @param string $sql
     * @return string
     */
    public static function classify($sql)
    {
        return preg_match('/\b(information_schema|pg_catalog)\b|\bpg_[a-z_]+\b/i', (string) $sql) === 1
            ? self::SCHEMA
            : self::DB;
    }
}
