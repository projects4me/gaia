<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Events\Notification\Email;
use Gaia\Events\Notification\Mailer;
use Gaia\Libraries\Utils\SmtpSettings;
use Gaia\Templates\Email\ResetPassword;
use Gaia\Templates\Email\WelcomePassword;
use Phalcon\Events\Event;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the notification emails with the mailer stubbed: no SMTP
 * connection is made (agaia-3959). Real delivery is agaia-3974.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class EmailNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
    }

    public function testWelcomePasswordIsSentToTheNewUser()
    {
        $email = new StubbedEmail(true);
        $user = new FakeUser();

        $email->emailPassword(new Event('emailPassword', $this), $user, 'S3cret!');

        $mail = $email->mailer;
        $this->assertSame(1, $mail->sendCount);
        $this->assertSame([['new.user@example.com', 'New User']], $mail->getToAddresses());
        $this->assertSame(WelcomePassword::getEmailSubject(), $mail->Subject);
        $this->assertStringContainsString('S3cret!', $mail->AltBody);
    }

    public function testFailedWelcomeSendDoesNotThrow()
    {
        $email = new StubbedEmail(false);

        $email->emailPassword(new Event('emailPassword', $this), new FakeUser(), 'S3cret!');

        $this->assertSame(1, $email->mailer->sendCount);
    }

    public function testResetPasswordSendsLinkAndSavesToken()
    {
        $email = new StubbedEmail(true, ['resetTokenTimeout' => 600]);
        $user = new FakeUser();

        $before = time();
        $email->resetPassword(new Event('resetPassword', $this), $user);

        $mail = $email->mailer;
        $this->assertSame(1, $mail->sendCount);
        $this->assertSame([['new.user@example.com', 'New User']], $mail->getToAddresses());
        $this->assertSame(ResetPassword::getEmailSubject(), $mail->Subject);
        $this->assertSame(40, strlen($user->resetToken));
        $this->assertStringContainsString($user->resetToken, $mail->AltBody);
        $this->assertSame(1, $user->saveCount);

        $expiry = strtotime($user->resetTokenExpiry . ' UTC');
        $this->assertGreaterThanOrEqual($before + 600, $expiry);
        $this->assertLessThanOrEqual(time() + 600, $expiry);
    }

    public function testFailedResetSendDoesNotThrowAndStillSavesToken()
    {
        // Unchanged behaviour: the token is saved even when sending fails.
        $email = new StubbedEmail(false);
        $user = new FakeUser();

        $email->resetPassword(new Event('resetPassword', $this), $user);

        $this->assertSame(1, $email->mailer->sendCount);
        $this->assertSame(1, $user->saveCount);
    }

    public function testMailerTimeoutIsOptional()
    {
        $smtp = SmtpSettings::resolve(['host' => 'mail.example.com', 'port' => 25, 'username' => 'u@example.com'], []);

        $this->assertSame((new PHPMailer())->Timeout, Mailer::create($smtp)->Timeout);
        $this->assertSame(5, Mailer::create($smtp, false, 5)->Timeout);
    }
}

/**
 * Email with fixed SMTP settings and a stub mailer.
 */
class StubbedEmail extends Email
{
    /** @var StubMailer|null */
    public $mailer;

    /** @var bool */
    private $sendResult;

    /** @var array */
    private $smtpOverrides;

    public function __construct($sendResult, array $smtpOverrides = [])
    {
        $this->sendResult = $sendResult;
        $this->smtpOverrides = $smtpOverrides;
    }

    protected function smtpSettings()
    {
        return array_merge(SmtpSettings::resolve([
            'host' => 'mail.example.com',
            'port' => 25,
            'username' => 'sender@example.com',
            'user' => 'Projects4me',
        ], []), $this->smtpOverrides);
    }

    protected function createMailer(array $smtp)
    {
        $this->mailer = new StubMailer($this->sendResult);
        return $this->mailer;
    }
}

/**
 * PHPMailer that records sends instead of connecting to a server.
 */
class StubMailer extends PHPMailer
{
    /** @var int */
    public $sendCount = 0;

    /** @var bool */
    private $result;

    public function __construct($result)
    {
        parent::__construct(false);
        $this->result = $result;
    }

    public function send()
    {
        $this->sendCount++;
        if (!$this->result) {
            $this->ErrorInfo = 'stubbed failure';
        }
        return $this->result;
    }
}

/**
 * The User fields the notifications read and write.
 */
class FakeUser
{
    public $name = 'New User';
    public $email = 'new.user@example.com';
    public $resetToken;
    public $resetTokenExpiry;
    public $saveCount = 0;

    public function save()
    {
        $this->saveCount++;
        return true;
    }
}
