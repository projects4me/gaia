<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Events\Notification;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends one test email with a given SMTP configuration and reports the
 * outcome as a code (Settings > Email "Test Settings", agaia-4017).
 *
 * The codes carry no server detail; the real error of an unexpected
 * failure is only logged.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Events
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TestEmailSender
{
    /**
     * The server accepted the test message.
     *
     * @var string
     */
    public const TEST_EMAIL_SENT = 'TEST_EMAIL_SENT';

    /**
     * The recipient is missing or not an email address; nothing was sent.
     *
     * @var string
     */
    public const INVALID_RECIPIENT = 'INVALID_RECIPIENT';

    /**
     * SMTP timeout in seconds, so an unreachable host fails within the request.
     *
     * @var int
     */
    public const TIMEOUT = 10;

    /**
     * @var MailerErrorMapper
     */
    private $errorMapper;

    /**
     * @param MailerErrorMapper|null $errorMapper
     */
    public function __construct(MailerErrorMapper $errorMapper = null)
    {
        $this->errorMapper = $errorMapper ?: new MailerErrorMapper();
    }

    /**
     * Send the test message.
     *
     * @param  mixed $recipient Address to send to
     * @param  array $smtp      Resolved SMTP configuration ({@see \Gaia\Libraries\Utils\SmtpSettings::resolve()})
     * @return string TEST_EMAIL_SENT, INVALID_RECIPIENT or a {@see MailerErrorMapper} code
     */
    public function send($recipient, array $smtp)
    {
        if (!is_string($recipient) || !PHPMailer::validateAddress(trim($recipient))) {
            return self::INVALID_RECIPIENT;
        }

        $mailer = $this->createMailer($smtp);
        $sent = $mailer->send(
            trim($recipient),
            '',
            'Projects4Me test email',
            '<p>This is a test email from Projects4Me. Your email settings work.</p>',
            'This is a test email from Projects4Me. Your email settings work.'
        );
        if ($sent) {
            return self::TEST_EMAIL_SENT;
        }

        $code = $this->errorMapper->toCode($mailer->getError());
        if ($code === MailerErrorMapper::UNKNOWN_ERROR) {
            global $logger;
            if (isset($logger)) {
                $logger->error('Test email could not be sent: ' . $mailer->getError());
            }
        }

        return $code;
    }

    /**
     * @param  array $smtp
     * @return Mailer
     */
    protected function createMailer(array $smtp)
    {
        return new Mailer(self::TIMEOUT, $smtp);
    }
}
