<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * Turns a PHP fatal error (e.g. memory exhausted) into a JSON 500 response.
 *
 * Fatal errors bypass the exception handlers in public/index.php. Without this the client gets
 * the PHP error text as an HTML page, often with HTTP 200, and reads it as a success. The error
 * details go to the application log only, never to the client.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia
 * @category Utils
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FatalErrorHandler
{
    /**
     * Error types that end the request.
     *
     * @var array
     */
    const FATAL_TYPES = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    /**
     * Message sent to the client.
     *
     * @var string
     */
    const CLIENT_MESSAGE = 'Internal Server Error';

    /**
     * Memory freed at shutdown so the handler can still run after memory is exhausted.
     *
     * @var string|null
     */
    private $reservedMemory;

    /**
     * @var \Phalcon\Logger|null
     */
    private $logger;

    /**
     * @param \Phalcon\Logger|null $logger
     */
    public function __construct($logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Registers the shutdown function that reports fatal errors.
     *
     * @return void
     */
    public function register()
    {
        $this->reservedMemory = str_repeat(' ', 64 * 1024);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Shutdown function: sends the JSON 500 when the request ended in a fatal error.
     *
     * @return void
     */
    public function handleShutdown()
    {
        $this->reservedMemory = null;

        $error = error_get_last();
        if (!$this->isFatal($error)) {
            return;
        }

        if ($this->logger) {
            $this->logger->critical(sprintf(
                'PHP fatal error: %s in %s on line %d',
                $error['message'],
                $error['file'],
                $error['line']
            ));
        }

        if (headers_sent()) {
            return;
        }

        $this->respond();
    }

    /**
     * Replaces the partial output of the failed request with the JSON 500.
     *
     * @return void
     */
    protected function respond()
    {
        // Drop any partial output of the failed request.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo $this->responseBody();
    }

    /**
     * Whether error_get_last() describes an error that ended the request.
     *
     * @param  array|null $error
     * @return bool
     */
    public function isFatal($error)
    {
        return is_array($error) && isset($error['type']) && in_array($error['type'], self::FATAL_TYPES, true);
    }

    /**
     * The JSON body sent to the client, in the shape of Gaia's other error responses.
     *
     * @return string
     */
    public function responseBody()
    {
        return json_encode(['error' => self::CLIENT_MESSAGE]);
    }
}
