<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Events\Notification;

use Gaia\Libraries\Utils\SmtpSettings;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Builds PHPMailer instances configured for SMTP from {@see SmtpSettings}
 * (saved settings first, then the `smtp` config).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Events
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Mailer
{
    /**
     * Create a mailer from the current SMTP settings.
     *
     * @param  bool     $exceptions Whether PHPMailer throws on errors
     * @param  int|null $timeout    SMTP timeout in seconds (null keeps PHPMailer's default)
     * @return PHPMailer
     */
    public static function fromSettings($exceptions = false, $timeout = null)
    {
        return self::create(SmtpSettings::load(), $exceptions, $timeout);
    }

    /**
     * Create a mailer from a resolved SMTP configuration.
     *
     * @param  array    $smtp       Output of {@see SmtpSettings::resolve()}
     * @param  bool     $exceptions Whether PHPMailer throws on errors
     * @param  int|null $timeout    SMTP timeout in seconds (null keeps PHPMailer's default)
     * @return PHPMailer
     */
    public static function create(array $smtp, $exceptions = false, $timeout = null)
    {
        $mail = new PHPMailer($exceptions);
        $mail->isSMTP();
        if ($timeout !== null) {
            $mail->Timeout = (int) $timeout;
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
