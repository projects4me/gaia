<?php

namespace Gaia\Events\Notification;

use Phalcon\Events\Event;
use Gaia\Libraries\Utils\SmtpSettings;
use Gaia\Templates\Email\ResetPassword;
use Gaia\Templates\Email\WelcomePassword;

/**
 * This class is used to send email notifications.
 *
 * SMTP comes from {@see Mailer} (Settings > Email, falling back to the smtp config).
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
            $smtp = SmtpSettings::load();
            $host = getenv('FRONTEND_HOST');
            $resetToken = $this->createResetToken(20);
            $mail = Mailer::create($smtp);
            ResetPassword::setEmailBody($user, $host, $resetToken);

            $mail->addAddress($user->email, $user->name);
            $mail->isHTML(true);
            $mail->Subject = ResetPassword::getEmailSubject();
            $mail->Body    = ResetPassword::getEmailBody($user, $host, $resetToken);
            $mail->AltBody = ResetPassword::getEmailAltBody($host, $resetToken);

            $this->send($mail, 'resetPassword');

            $this->saveResetToken($user, $resetToken, $smtp['resetTokenTimeout']);
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception("Message could not be sent. Mailer Error: {$e->getMessage()}");
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
            $mail = Mailer::fromSettings();
            $host = getenv('FRONTEND_HOST');
            WelcomePassword::setEmailBody($user, $host, $password);

            $mail->addAddress($user->email, $user->name);
            $mail->isHTML(true);
            $mail->Subject = WelcomePassword::getEmailSubject();
            $mail->Body    = WelcomePassword::getEmailBody();
            $mail->AltBody = WelcomePassword::getEmailAltBody($user, $password);

            $this->send($mail, 'emailPassword');
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception("Message could not be sent. Mailer Error: {$e->getMessage()}");
        }
    }

    /**
     * Send a message. A failed send is logged, not thrown, so the user's
     * request still succeeds (side effects fail open), as before.
     *
     * @method send
     * @param  \PHPMailer\PHPMailer\PHPMailer $mail
     * @param  string                         $context
     * @return bool
     */
    protected function send($mail, $context)
    {
        global $logger;

        if ($mail->send()) {
            return true;
        }

        if (isset($logger)) {
            $logger->error("Email {$context} could not be sent: {$mail->ErrorInfo}");
        }
        return false;
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
