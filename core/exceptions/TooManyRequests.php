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
    use SendsErrorResponse;

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
     * Build the http response, with Retry-After, without sending it.
     *
     * @param \Phalcon\Http\Response|null $response the response to build on; a new one when null
     * @return \Phalcon\Http\Response
     */
    public function toResponse(?\Phalcon\Http\Response $response = null): \Phalcon\Http\Response
    {
        return ErrorResponse::build(429, 'Too Many Requests', [
            'error' => 'tooManyRequests',
            'error_description' => $this->getMessage()
        ], ['Retry-After' => (string) $this->retryAfter], $response);
    }
}
