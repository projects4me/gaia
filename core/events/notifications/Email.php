<?php

namespace Gaia\Events\Notification;

use Phalcon\Events\Event;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Gaia\Templates\Email\ResetPassword;
use Gaia\Templates\Email\WelcomePassword;

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
            $mail->Body    = ResetPassword::getEmailBody($user, $host, $resetToken);
            $mail->AltBody = ResetPassword::getEmailAltBody($host, $resetToken);

            $mail->send();

            $this->saveResetToken($user, $resetToken, $smtpConfig['resetTokenTimeout']);
        } catch (Exception $e) {
            throw new \Gaia\Exception\Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }

    /**
     * Sends the generated password to a newly created user via email.
     *
     * Fired as notifications:emailPassword by GeneratePasswordComponent::afterCreate().
     * The event data argument carries the plain-text password before it is
     * hashed by encryptPasswordBehavior, so the value is still readable here.
     *
     * @method emailPassword
     * @param  Event                 $event
     * @param  \Gaia\MVC\Models\User $user
     * @param  string                $password
     * @return void
     * @throws \Exception
     */
    public function emailPassword(Event $event, $user, $password)
    {
        try {
            $di = \Phalcon\Di::getDefault();
            $smtpConfig = $di->get('config')->get('smtp');
            $mail = new PHPMailer();
            $host = getenv('FRONTEND_HOST');
            WelcomePassword::setEmailBody($user, $host, $password);

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
            $mail->Subject = WelcomePassword::getEmailSubject();
            $mail->Body    = WelcomePassword::getEmailBody();
            $mail->AltBody = WelcomePassword::getEmailAltBody($user, $password);

            $mail->send();
        } catch (Exception $e) {
            throw new \Gaia\Exception\Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
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

    /**
     * This function is used to save the reset token in the database.
     *
     * @method saveResetToken
     * @param  \Gaia\MVC\Models\User $user
     * @param string                $resetToken
     * @param string                $resetTokenTimeout
     */
    public function saveResetToken($user, $resetToken, $resetTokenTimeout)
    {
        $user->resetToken = $resetToken;
        $user->resetTokenExpiry = gmdate('Y-m-d H:i:s', strtotime("+$resetTokenTimeout seconds"));
        $user->save();
    }
}
