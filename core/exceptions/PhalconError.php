<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * This class is responsible for turning a framework (Phalcon) exception into a
 * JSON error response.
 *
 * Dispatcher not-found errors become 404, everything else 500. The raw Phalcon
 * message can name tables, columns or PHQL, so it is only returned when debug
 * is on.
 *
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PhalconError
{
    /**
     * Dispatcher exception codes that mean the requested resource does not exist.
     *
     * @var int[]
     */
    private const NOT_FOUND_CODES = [
        \Phalcon\Dispatcher\Exception::EXCEPTION_HANDLER_NOT_FOUND,
        \Phalcon\Dispatcher\Exception::EXCEPTION_ACTION_NOT_FOUND,
    ];

    /**
     * @var \Phalcon\Exception
     */
    private $exception;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @param \Phalcon\Exception $exception
     * @param bool $debug whether the raw Phalcon message may be returned
     */
    public function __construct(\Phalcon\Exception $exception, bool $debug = false)
    {
        $this->exception = $exception;
        $this->debug = $debug;
    }

    /**
     * Whether the exception means the requested resource was not found.
     *
     * @return bool
     */
    public function isNotFound(): bool
    {
        return $this->exception instanceof \Phalcon\Dispatcher\Exception
            && in_array($this->exception->getCode(), self::NOT_FOUND_CODES, true);
    }

    /**
     * The HTTP status code for the exception.
     *
     * @return int
     */
    public function statusCode(): int
    {
        return $this->isNotFound() ? 404 : 500;
    }

    /**
     * The JSON error body, in the envelope the other Gaia exceptions use.
     *
     * @return array
     */
    public function body(): array
    {
        if ($this->isNotFound()) {
            $body = [
                'error' => 'resourceNotFound',
                'error_description' => 'The requested resource was not found',
            ];
        } else {
            $body = [
                'error' => 'internalServerError',
                'error_description' => 'An internal server error occurred',
            ];
        }

        if ($this->debug) {
            $body['error_description'] = $this->exception->getMessage();
        }

        return $body;
    }

    /**
     * This function is used to set and return the http response.
     *
     * @param \Phalcon\Http\Response|null $response defaults to the DI response
     * @return \Phalcon\Http\Response
     */
    public function handle($response = null)
    {
        $response = $response ?? \Phalcon\Di::getDefault()->get('response');
        $response->setStatusCode($this->statusCode(), $this->isNotFound() ? 'Not Found' : 'Internal Server Error');
        $response->setJsonContent($this->body());
        $response->send();
        return $response;
    }
}
