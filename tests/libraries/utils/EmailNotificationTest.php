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
 * Unit tests for the notification emails and the Mailer, with delivery
 * stubbed: no SMTP connection is made (agaia-3959). Real delivery is
 * agaia-3974.
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
        $email = new StubbedEmail(new FakeMailer(true));

        $email->emailPassword(new Event('emailPassword', $this), new FakeUser(), 'S3cret!');

        $sent = $email->mailer->sent;
        $this->assertCount(1, $sent);
        $this->assertSame('new.user@example.com', $sent[0]['toEmail']);
        $this->assertSame('New User', $sent[0]['toName']);
        $this->assertSame(WelcomePassword::getEmailSubject(), $sent[0]['subject']);
        $this->assertStringContainsString('S3cret!', $sent[0]['altBody']);
    }

    public function testFailedWelcomeSendDoesNotThrow()
    {
        $email = new StubbedEmail(new FakeMailer(false));

        $email->emailPassword(new Event('emailPassword', $this), new FakeUser(), 'S3cret!');

        $this->assertCount(1, $email->mailer->sent);
    }

    public function testResetPasswordSendsLinkAndSavesToken()
    {
        $email = new StubbedEmail(new FakeMailer(true), 600);
        $user = new FakeUser();

        $before = time();
        $email->resetPassword(new Event('resetPassword', $this), $user);

        $sent = $email->mailer->sent;
        $this->assertCount(1, $sent);
        $this->assertSame('new.user@example.com', $sent[0]['toEmail']);
        $this->assertSame(ResetPassword::getEmailSubject(), $sent[0]['subject']);
        $this->assertSame(40, strlen($user->resetToken));
        $this->assertStringContainsString($user->resetToken, $sent[0]['altBody']);
        $this->assertSame(1, $user->saveCount);

        $expiry = strtotime($user->resetTokenExpiry . ' UTC');
        $this->assertGreaterThanOrEqual($before + 600, $expiry);
        $this->assertLessThanOrEqual(time() + 600, $expiry);
    }

    public function testFailedResetSendDoesNotThrowAndStillSavesToken()
    {
        // Unchanged behaviour: the token is saved even when sending fails.
        $email = new StubbedEmail(new FakeMailer(false));
        $user = new FakeUser();

        $email->resetPassword(new Event('resetPassword', $this), $user);

        $this->assertCount(1, $email->mailer->sent);
        $this->assertSame(1, $user->saveCount);
    }

    public function testMailerSendBuildsTheMessage()
    {
        $mailer = new StubTransportMailer(true);

        $this->assertTrue($mailer->send('to@example.com', 'To', 'Subject', '<p>Hi</p>', 'Hi'));

        $mail = $mailer->transport;
        $this->assertSame([['to@example.com', 'To']], $mail->getToAddresses());
        $this->assertSame('Subject', $mail->Subject);
        $this->assertSame('<p>Hi</p>', $mail->Body);
        $this->assertSame('Hi', $mail->AltBody);
        $this->assertSame('text/html', $mail->ContentType);
        $this->assertSame('', $mailer->getError());
    }

    public function testMailerReportsTheTransportError()
    {
        $mailer = new StubTransportMailer(false);

        $this->assertFalse($mailer->send('to@example.com', 'To', 'Subject', '<p>Hi</p>', 'Hi'));
        $this->assertSame('stubbed failure', $mailer->getError());
    }
}

/**
 * Email with a given mailer and reset-link lifetime.
 */
class StubbedEmail extends Email
{
    /** @var FakeMailer */
    public $mailer;

    /** @var int */
    private $timeout;

    public function __construct(FakeMailer $mailer, $resetTokenTimeout = 3600)
    {
        $this->mailer = $mailer;
        $this->timeout = $resetTokenTimeout;
    }

    protected function createMailer()
    {
        return $this->mailer;
    }

    protected function resetTokenTimeout()
    {
        return $this->timeout;
    }
}

/**
 * Mailer that records messages instead of delivering them.
 */
class FakeMailer extends Mailer
{
    /** @var array */
    public $sent = [];

    /** @var bool */
    private $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function send($toEmail, $toName, $subject, $htmlBody, $altBody)
    {
        $this->sent[] = compact('toEmail', 'toName', 'subject', 'htmlBody', 'altBody');
        $this->error = $this->result ? '' : 'stubbed failure';
        return $this->result;
    }
}

/**
 * Mailer whose transport is a stub PHPMailer, to test send() itself.
 */
class StubTransportMailer extends Mailer
{
    /** @var StubPhpMailer|null */
    public $transport;

    /** @var bool */
    private $result;

    public function __construct($result)
    {
        parent::__construct(null, SmtpSettings::resolve([
            'host' => 'mail.example.com',
            'port' => 25,
            'username' => 'sender@example.com',
            'user' => 'Projects4me',
        ], []));
        $this->result = $result;
    }

    protected function createTransport()
    {
        $this->transport = new StubPhpMailer($this->result);
        return $this->transport;
    }
}

/**
 * PHPMailer that never connects.
 */
class StubPhpMailer extends PHPMailer
{
    /** @var bool */
    private $result;

    public function __construct($result)
    {
        parent::__construct(false);
        $this->result = $result;
    }

    public function send()
    {
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
