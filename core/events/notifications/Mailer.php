<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Events\Notification;

use Gaia\Libraries\Utils\SmtpSettings;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends email for the notifications.
 *
 * Owns the transport: today SMTP through PHPMailer, configured from
 * {@see SmtpSettings} (Settings > Email first, then the smtp config).
 * Callers only hand over a message, so switching transport (e.g. to an
 * HTTP API) only changes this class.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Events
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Mailer
{
    /**
     * Resolved SMTP configuration, loaded on first use.
     *
     * @var array|null
     */
    protected $smtp;

    /**
     * SMTP timeout in seconds (null keeps PHPMailer's default).
     *
     * @var int|null
     */
    protected $timeout;

    /**
     * Error from the last failed send.
     *
     * @var string
     */
    protected $error = '';

    /**
     * @param int|null   $timeout SMTP timeout in seconds (null keeps PHPMailer's default)
     * @param array|null $smtp    Resolved SMTP configuration; null reads the current settings
     */
    public function __construct($timeout = null, array $smtp = null)
    {
        $this->timeout = $timeout;
        $this->smtp = $smtp;
    }

    /**
     * Send one HTML message with a plain-text alternative.
     *
     * @param  string $toEmail
     * @param  string $toName
     * @param  string $subject
     * @param  string $htmlBody
     * @param  string $altBody
     * @return bool Whether the server accepted the message; see getError() when false
     */
    public function send($toEmail, $toName, $subject, $htmlBody, $altBody)
    {
        $this->error = '';

        $mail = $this->createTransport();
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody;

        if ($mail->send()) {
            return true;
        }

        $this->error = $mail->ErrorInfo;
        return false;
    }

    /**
     * Error from the last failed send (empty after a successful one).
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Build the PHPMailer instance for one message from the SMTP configuration.
     *
     * @return PHPMailer
     */
    protected function createTransport()
    {
        if ($this->smtp === null) {
            $this->smtp = SmtpSettings::load();
        }
        $smtp = $this->smtp;

        $mail = new PHPMailer(false);
        $mail->isSMTP();
        if ($this->timeout !== null) {
            $mail->Timeout = (int) $this->timeout;
        }
        $mail->Host = (string) $smtp['host'];
        $mail->Port = (int) $smtp['port'];
        $mail->SMTPAuth = (bool) $smtp['auth'];
        $mail->Username = (string) $smtp['username'];
        $mail->Password = (string) $smtp['password'];

        if ($smtp['encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtp['encryption'] === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom((string) $smtp['fromEmail'], (string) $smtp['fromName']);

        return $mail;
    }
}
