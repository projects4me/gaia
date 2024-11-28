<?php

namespace Gaia\Events\Notification;

use Phalcon\Events\Event;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Gaia\Templates\Email\ResetPassword;

/**
 * This class is used to send email notifications.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Events
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Email
{
    /**
     * This function is used to send the password reset link to the user.
     *
     * @method resetPassword
     * @param  Event                 $event
     * @param  \Gaia\MVC\Models\User $user
     * @return void
     * @throws \Exception
     */
    public function resetPassword(Event $event, $user)
    {
        try {
            $di = \Phalcon\Di::getDefault();
            $smtpConfig = $di->get('config')->get('smtp');
            $request = \OAuth2\Request::createFromGlobals();
            $host = getenv('FRONTEND_HOST');
            $resetToken = $this->createResetToken(20);
            $mail = new PHPMailer();
            ResetPassword::setEmailBody($user, $host, $resetToken);

            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->isSMTP();
            $mail->Host       = $smtpConfig['host'];
            $mail->SMTPAuth   = $smtpConfig['auth'];
            $mail->Username   = $smtpConfig['username'];
            $mail->Password   = $smtpConfig['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtpConfig['port'];

            $mail->setFrom($smtpConfig['username'], $smtpConfig['user']);
            $mail->addAddress($user->email, $user->name);
            $mail->isHTML(true);
            $mail->Subject = ResetPassword::getEmailSubject();
            $mail->Body    = ResetPassword::getEmailBody();
            // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            $mail->send();

            // TODO: Save the reset token in the database
            // $this->saveResetToken($user, $resetToken);
        } catch (Exception $e) {
            // TODO: Handle this exception using our classes
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }

    /**
     * This function is used to create a reset token depending on the count.
     *
     * @method createResetToken
     * @param  int $count
     * @return string
     */
    public function createResetToken($count)
    {
        return bin2hex(random_bytes($count));
    }
}
