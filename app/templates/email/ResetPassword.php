<?php

namespace Gaia\Templates\Email;

class ResetPassword
{
    /**
     * This is the subject of the email
     *
     * @var string
     */
    protected static $subject = 'Reset Link';

    /**
     * This is the body of the email
     *
     * @var string
     */
    protected static $body = '';

    /**
     * This function is used to get the subject of the email.
     *
     * @method getEmailSubject
     */
    public static function getEmailSubject()
    {
        return self::$subject;
    }

    /**
     * This function is used to set the body of the email.
     *
     * @param  \Gaia\Models\User $user
     * @param  string            $host
     * @param  string            $resetToken
     * @method setEmailBody
     * @return string
     */
    public static function setEmailBody($user, $host, $resetToken)
    {
        self::$body = '
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
    }

    /**
     * This function is used to get the body of the email.
     *
     * @method getBody
     * @return string
     */
    public static function getEmailBody()
    {
        return self::$body;
    }

    /**
     * This function is used to get the alternative body of the email.
     *
     * @param  string $host
     * @param  string $resetToken
     * @method getEmailAltBody
     * @return string
     */
    public static function getEmailAltBody($host, $resetToken)
    {
        return "Click on the link below to reset your password. <br> http://$host/resetpassword?token=$resetToken";
    }
}
