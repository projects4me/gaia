<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * A fatal PHP error, as reported by error_get_last() at shutdown.
 *
 * Fatal errors (a class that fails to load, a signature mismatch, running out
 * of memory, ...) are not Throwables, so the try/catch in public/index.php and
 * cli.php never sees them. The shutdown handlers use this class to tell a fatal
 * error from a warning or notice and to describe it.
 *
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FatalError
{
    /**
     * The error types that end the script, mapped to their names.
     *
     * @var array<int,string>
     */
    public const TYPES = [
        E_ERROR => 'E_ERROR',
        E_PARSE => 'E_PARSE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_USER_ERROR => 'E_USER_ERROR',
    ];

    /**
     * @var int
     */
    private $type;

    /**
     * @var string
     */
    private $message;

    /**
     * @var string
     */
    private $file;

    /**
     * @var int
     */
    private $line;

    /**
     * @param int $type
     * @param string $message
     * @param string $file
     * @param int $line
     */
    public function __construct(int $type, string $message, string $file, int $line)
    {
        $this->type = $type;
        $this->message = $message;
        $this->file = $file;
        $this->line = $line;
    }

    /**
     * Builds the fatal error from an error_get_last() array.
     *
     * @param array|null $lastError the result of error_get_last()
     * @return self|null null when there was no error or it was not fatal
     */
    public static function fromLastError(?array $lastError): ?self
    {
        if ($lastError === null || !isset($lastError['type']) || !isset(self::TYPES[$lastError['type']])) {
            return null;
        }

        return new self(
            (int) $lastError['type'],
            (string) ($lastError['message'] ?? ''),
            (string) ($lastError['file'] ?? ''),
            (int) ($lastError['line'] ?? 0)
        );
    }

    /**
     * The raw error message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * A one-line description for the log: type, message and file:line.
     *
     * @return string
     */
    public function describe(): string
    {
        return sprintf('%s: %s in %s:%d', self::TYPES[$this->type], $this->message, $this->file, $this->line);
    }

    /**
     * The error as a Throwable, so PhalconError can build the JSON response.
     *
     * @return \ErrorException
     */
    public function toThrowable(): \ErrorException
    {
        return new \ErrorException($this->message, 0, $this->type, $this->file, $this->line);
    }
}
