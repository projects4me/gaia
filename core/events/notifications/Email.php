<?php

namespace Gaia\Events\Notification;

use Phalcon\Events\Event;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

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
            $request = \OAuth2\Request::createFromGlobals();
            $host = getenv('FRONTEND_HOST');
            $resetToken = $this->createResetToken(20);
            $mail = new PHPMailer();

            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'projectsfourme@gmail.com';
            $mail->Password   = 'acgl zdcf txkc oixp';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            //Recipients
            $mail->setFrom('projectsfourme@gmail.com', 'Projects4me');
            $mail->addAddress($user->email, $user->name);

            //Content
            $mail->isHTML(true);
            $mail->Subject = 'Reset Link';
            $mail->Body    = '
                <div style="max-width:600px;margin:0 auto;background-color:#ffffff;">
                    <div style="background-color:#f5f8fa;padding:20px 10px;">
                        <h1 style="color:#333333;font-size:24px;">Projects4me</h1>
                    </div>
                    <div style="padding:20px 10px;">
                        <p>Dear ' . $user->name . ',</p>
                        <p>Click on the link below to reset your password.</p>
                        <a href="http://' .$host. '/resetpassword?token=' . $resetToken . '">Reset Password</a>
                    </div>
                </div>
            ';
            // $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            $mail->send();

            // TODO: Save the reset token in the database
            // $this->saveResetToken($user, $resetToken);
        } catch (Exception $e) {
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
