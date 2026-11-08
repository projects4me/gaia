<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * A caller made more requests than it may in a window: 429 Too Many Requests,
 * with the seconds to wait in Retry-After.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TooManyRequests extends \Exception
{
    /**
     * Seconds the caller should wait before trying again.
     *
     * @var int
     */
    private $retryAfter;

    /**
     * @param string $message
     * @param int    $retryAfter Seconds to wait
     */
    public function __construct($message = '', $retryAfter = 60)
    {
        parent::__construct($message);
        $this->retryAfter = (int) $retryAfter;
    }

    /**
     * Seconds the caller should wait before trying again.
     *
     * @return int
     */
    public function getRetryAfter()
    {
        return $this->retryAfter;
    }

    /**
     * This function is used to set and return the http response.
     *
     * @return \Phalcon\Http\Response
     */
    public function handle()
    {
        $di = \Phalcon\Di::getDefault();
        $response = $di->get('response');
        $response->setStatusCode(429, "Too Many Requests");
        $response->setHeader('Retry-After', (string) $this->retryAfter);
        $response->setJsonContent([
            'error' => 'tooManyRequests',
            'error_description' => $this->getMessage()
        ]);
        $response->send();
        return $response;
    }
}
