<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Events\Notification;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Maps a failed send ({@see Mailer::getError()}) to a result code clients
 * can translate. The codes carry no server detail.
 *
 * PHPMailer reports failures as text in its current language; Mailer uses
 * the default one, so the messages are matched against its translations.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Events
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MailerErrorMapper
{
    /**
     * The server rejected the credentials.
     *
     * @var string
     */
    public const SMTP_AUTH_FAILED = 'SMTP_AUTH_FAILED';

    /**
     * The host or port can't be reached, or TLS/SSL fails.
     *
     * @var string
     */
    public const SMTP_CONNECTION_FAILED = 'SMTP_CONNECTION_FAILED';

    /**
     * Any other failure.
     *
     * @var string
     */
    public const UNKNOWN_ERROR = 'UNKNOWN_ERROR';

    /**
     * PHPMailer language keys and the code each one maps to.
     *
     * @var array
     */
    private const CODES = [
        'authenticate' => self::SMTP_AUTH_FAILED,
        'connect_host' => self::SMTP_CONNECTION_FAILED,
        'smtp_connect_failed' => self::SMTP_CONNECTION_FAILED,
    ];

    /**
     * PHPMailer messages keyed by language key.
     *
     * @var array
     */
    private $messages;

    /**
     * @param array|null $messages PHPMailer translations; null uses the default language
     */
    public function __construct(array $messages = null)
    {
        $this->messages = $messages === null ? (new PHPMailer())->getTranslations() : $messages;
    }

    /**
     * Map a Mailer error to its code.
     *
     * @param  string $error The error text from the failed send
     * @return string One of the class constants
     */
    public function toCode($error)
    {
        $error = (string) $error;

        foreach (self::CODES as $key => $code) {
            // PHPMailer may append the server's reply after the message.
            if (isset($this->messages[$key]) && $this->messages[$key] !== ''
                && strpos($error, $this->messages[$key]) === 0
            ) {
                return $code;
            }
        }

        return self::UNKNOWN_ERROR;
    }
}
