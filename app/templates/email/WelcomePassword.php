<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Templates\Email;

/**
 * Email template for sending a newly created user their generated password.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Templates
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WelcomePassword
{
    /**
     * The subject of the email.
     *
     * @var string
     */
    protected static $subject = 'Your account has been created';

    /**
     * The HTML body of the email.
     *
     * @var string
     */
    protected static $body = '';

    /**
     * Returns the email subject.
     *
     * @method getEmailSubject
     * @return string
     */
    public static function getEmailSubject()
    {
        return self::$subject;
    }

    /**
     * Builds and stores the HTML email body.
     *
     * @param  \Gaia\MVC\Models\User $user
     * @param  string                $host
     * @param  string                $password
     * @method setEmailBody
     * @return void
     */
    public static function setEmailBody($user, $host, $password)
    {
        self::$body = '
        <div style="max-width:600px;margin:0 auto;background-color:#ffffff;">
            <div style="background-color:#f5f8fa;padding:20px 10px;">
                <h1 style="color:#333333;font-size:24px;text-align:center;">Projects4me</h1>
            </div>
            <div style="padding:20px 10px;">
                <p>Dear ' . $user->name . ',</p>
                <p>Your account has been created. You can log in using the credentials below.</p>
                <p><strong>Email:</strong> ' . $user->email . '</p>
                <p><strong>Password:</strong> ' . $password . '</p>
                <p>For security, please change your password after your first <a href="//' .$host. '/signin">login</a>.</p>
            </div>
        </div>
        ';
    }

    /**
     * Returns the stored HTML email body.
     *
     * @method getEmailBody
     * @return string
     */
    public static function getEmailBody()
    {
        return self::$body;
    }

    /**
     * Returns a plain-text fallback body.
     *
     * @param  \Gaia\MVC\Models\User $user
     * @param  string                $password
     * @method getEmailAltBody
     * @return string
     */
    public static function getEmailAltBody($user, $password)
    {
        return "Dear {$user->name}, your account has been created. Email: {$user->email} | Password: {$password}. Please change your password after your first login.";
    }
}
