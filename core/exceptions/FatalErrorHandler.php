<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * Shutdown handler for HTTP requests: logs a fatal PHP error and answers a
 * JSON 500 instead of an empty 200.
 *
 * public/index.php lowers error_reporting and a fatal error is not a
 * Throwable, so without this handler the client gets an empty 200 and nothing
 * is logged. The response body is the one PhalconError builds for any other
 * uncaught error; the raw message is only returned when debug is on.
 *
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FatalErrorHandler
{
    /**
     * @var object|null the Gaia logger (anything with an error() method)
     */
    private $logger;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @param object|null $logger the Gaia logger; error_log() is used when null
     * @param bool $debug whether the raw message may be returned
     */
    public function __construct($logger = null, bool $debug = false)
    {
        $this->logger = $logger;
        $this->debug = $debug;
    }

    /**
     * Registers the handler to run at shutdown. The logger and the debug
     * setting are read then, so it can be registered before either is built.
     *
     * @return void
     */
    public static function register(): void
    {
        register_shutdown_function(
            static function () {
                $debug = ($GLOBALS['settings']['application']['debug'] ?? false) === true;
                $lastError = error_get_last();
                $headersSent = headers_sent();
                if (!$headersSent && FatalError::fromLastError($lastError) !== null) {
                    // Drop whatever was buffered before the error (a half-rendered
                    // body or PHP's own error text) so the client only gets the JSON.
                    while (ob_get_level() > 0 && @ob_end_clean()) {
                    }
                }
                $handler = new self($GLOBALS['logger'] ?? null, $debug);
                $handler->handle($lastError, $headersSent);
            }
        );
    }

    /**
     * Logs a fatal error and, when the headers have not been sent yet,
     * answers a JSON 500.
     *
     * @param array|null $lastError the result of error_get_last()
     * @param bool $headersSent whether the response headers are already out
     * @param \Phalcon\Http\Response|null $response the response to send
     * @return \Phalcon\Http\Response|null the sent response, null when none was sent
     */
    public function handle(?array $lastError, bool $headersSent, $response = null)
    {
        $fatalError = FatalError::fromLastError($lastError);
        if ($fatalError === null) {
            return null;
        }

        $this->log($fatalError);

        if ($headersSent) {
            return null;
        }

        try {
            $error = new PhalconError($fatalError->toThrowable(), $this->debug);
            return $error->handle($response ?? new \Phalcon\Http\Response());
        } catch (\Throwable $e) {
            // The fatal error is already logged; don't let a failing response mask it.
            error_log('Fatal error handler could not send the response: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Writes the fatal error to the Gaia logger, or to error_log() if the
     * logger is not built yet or fails.
     *
     * @param FatalError $fatalError
     * @return void
     */
    private function log(FatalError $fatalError): void
    {
        $message = 'Fatal error ' . $fatalError->describe();

        if ($this->logger !== null) {
            try {
                $this->logger->error($message);
                return;
            } catch (\Throwable $e) {
                // fall through to error_log
            }
        }

        error_log($message);
    }
}
