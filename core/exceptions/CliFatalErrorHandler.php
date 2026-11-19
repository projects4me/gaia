<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * Shutdown handler for cli.php: writes a fatal PHP error to stderr and makes
 * the command exit non-zero.
 *
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CliFatalErrorHandler
{
    /**
     * The exit code for a command that ended on a fatal error.
     */
    public const EXIT_CODE = 1;

    /**
     * Registers the handler to run at shutdown.
     *
     * @return void
     */
    public static function register(): void
    {
        register_shutdown_function(
            static function () {
                $exitCode = (new self())->handle(error_get_last(), STDERR);
                if ($exitCode !== 0) {
                    exit($exitCode);
                }
            }
        );
    }

    /**
     * Writes a fatal error to the stream.
     *
     * @param array|null $lastError the result of error_get_last()
     * @param resource $stream where to write the error, STDERR at shutdown
     * @return int the exit code: 0 when there was no fatal error
     */
    public function handle(?array $lastError, $stream): int
    {
        $fatalError = FatalError::fromLastError($lastError);
        if ($fatalError === null) {
            return 0;
        }

        fwrite($stream, 'Fatal error ' . $fatalError->describe() . PHP_EOL);
        return self::EXIT_CODE;
    }
}
