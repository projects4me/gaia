<?php

namespace Gaia\Events\Notification;

use Phalcon\Events\Event;
use Gaia\Templates\Email\ResetPassword;
use Gaia\Templates\Email\WelcomePassword;

/**
 * This class is used to send email notifications.
 *
 * It builds the messages; delivery (and its settings) belongs to {@see Mailer}.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Events
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Email
{
    /**
     * Reset link lifetime when the config doesn't set one (seconds).
     *
     * @var int
     */
    public const DEFAULT_RESET_TOKEN_TIMEOUT = 3600;

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
            $host = getenv('FRONTEND_HOST');
            $resetToken = $this->createResetToken(20);
            ResetPassword::setEmailBody($user, $host, $resetToken);

            $this->send(
                'resetPassword',
                $user,
                ResetPassword::getEmailSubject(),
                ResetPassword::getEmailBody($user, $host, $resetToken),
                ResetPassword::getEmailAltBody($host, $resetToken)
            );

            $this->saveResetToken($user, $resetToken, $this->resetTokenTimeout());
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
            $host = getenv('FRONTEND_HOST');
            WelcomePassword::setEmailBody($user, $host, $password);

            $this->send(
                'emailPassword',
                $user,
                WelcomePassword::getEmailSubject(),
                WelcomePassword::getEmailBody(),
                WelcomePassword::getEmailAltBody($user, $password)
            );
        } catch (\Exception $e) {
            throw new \Gaia\Exception\Exception("Message could not be sent. Mailer Error: {$e->getMessage()}");
        }
    }

    /**
     * The mailer that delivers the messages.
     *
     * @method createMailer
     * @return Mailer
     */
    protected function createMailer()
    {
        return new Mailer();
    }

    /**
     * Send a message to the user. A failed send is logged, not thrown, so the
     * user's request still succeeds (side effects fail open), as before.
     *
     * @method send
     * @param  string                $context
     * @param  \Gaia\MVC\Models\User $user
     * @param  string                $subject
     * @param  string                $htmlBody
     * @param  string                $altBody
     * @return bool
     */
    protected function send($context, $user, $subject, $htmlBody, $altBody)
    {
        global $logger;

        $mailer = $this->createMailer();
        if ($mailer->send($user->email, $user->name, $subject, $htmlBody, $altBody)) {
            return true;
        }

        if (isset($logger)) {
            $logger->error("Email {$context} could not be sent: {$mailer->getError()}");
        }
        return false;
    }

    /**
     * How long a reset link stays valid, in seconds (smtp.resetTokenTimeout).
     *
     * @method resetTokenTimeout
     * @return int
     */
    protected function resetTokenTimeout()
    {
        $config = \Phalcon\Di::getDefault()->get('config')->get('smtp');
        return isset($config['resetTokenTimeout'])
            ? (int) $config['resetTokenTimeout']
            : self::DEFAULT_RESET_TOKEN_TIMEOUT;
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
