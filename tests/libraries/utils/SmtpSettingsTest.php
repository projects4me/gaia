<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Events\Notification\Mailer;
use Gaia\Libraries\Utils\SmtpSettings;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

/**
 * Saved email settings override the smtp config; unset settings fall back
 * to it (agaia-3959). Also checks how Mailer maps the result onto PHPMailer.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SmtpSettingsTest extends TestCase
{
    /**
     * The shape of core/config/smtp.php.
     *
     * @var array
     */
    private $config = [
        'host' => 'smtp.gmail.com',
        'user' => 'Projects4me',
        'auth' => true,
        'port' => 587,
        'username' => 'env-user@example.com',
        'password' => 'env-secret',
        'resetTokenTimeout' => 3600,
    ];

    public function testNoSettingsMatchesTodaysConfig()
    {
        $smtp = SmtpSettings::resolve($this->config, []);

        $this->assertSame('smtp.gmail.com', $smtp['host']);
        $this->assertSame(587, $smtp['port']);
        $this->assertSame('tls', $smtp['encryption']);
        $this->assertTrue($smtp['auth']);
        $this->assertSame('env-user@example.com', $smtp['username']);
        $this->assertSame('env-secret', $smtp['password']);
        $this->assertSame('Projects4me', $smtp['fromName']);
        $this->assertSame('env-user@example.com', $smtp['fromEmail']);
        $this->assertArrayNotHasKey('resetTokenTimeout', $smtp);
    }

    public function testBlankAndNullSettingsFallBackToConfig()
    {
        $smtp = SmtpSettings::resolve($this->config, [
            'email.smtpHost' => null,
            'email.smtpPort' => '',
            'email.smtpUsername' => '   ',
            'email.smtpPassword' => null,
        ]);

        $this->assertSame('smtp.gmail.com', $smtp['host']);
        $this->assertSame(587, $smtp['port']);
        $this->assertSame('env-user@example.com', $smtp['username']);
        $this->assertSame('env-secret', $smtp['password']);
    }

    public function testSavedSettingsWin()
    {
        $smtp = SmtpSettings::resolve($this->config, [
            'email.smtpHost' => 'mail.example.com',
            'email.smtpPort' => '465',
            'email.smtpEncryption' => 'SSL',
            'email.smtpUsername' => 'db-user@example.com',
            'email.smtpPassword' => 'db-secret',
            'email.fromName' => 'Acme PM',
            'email.fromEmail' => 'noreply@example.com',
        ]);

        $this->assertSame('mail.example.com', $smtp['host']);
        $this->assertSame(465, $smtp['port']);
        $this->assertSame('ssl', $smtp['encryption']);
        $this->assertSame('db-user@example.com', $smtp['username']);
        $this->assertSame('db-secret', $smtp['password']);
        $this->assertSame('Acme PM', $smtp['fromName']);
        $this->assertSame('noreply@example.com', $smtp['fromEmail']);
    }

    public function testSenderDefaultsToTheEffectiveUsername()
    {
        $smtp = SmtpSettings::resolve($this->config, ['email.smtpUsername' => 'db-user@example.com']);

        $this->assertSame('db-user@example.com', $smtp['fromEmail']);
    }

    public function testUnknownEncryptionFallsBackToTls()
    {
        $smtp = SmtpSettings::resolve($this->config, ['email.smtpEncryption' => 'starttls-please']);

        $this->assertSame('tls', $smtp['encryption']);
    }

    /**
     * @dataProvider encryptionModes
     */
    public function testMailerMapsEncryption($encryption, $secure, $autoTls)
    {
        $smtp = SmtpSettings::resolve($this->config, ['email.smtpEncryption' => $encryption]);
        $mail = (new TransportMailer(null, $smtp))->transport();

        $this->assertSame($secure, $mail->SMTPSecure);
        $this->assertSame($autoTls, $mail->SMTPAutoTLS);
    }

    public function encryptionModes()
    {
        return [
            'tls' => ['tls', PHPMailer::ENCRYPTION_STARTTLS, true],
            'ssl' => ['ssl', PHPMailer::ENCRYPTION_SMTPS, true],
            'none' => ['none', '', false],
        ];
    }

    public function testMailerUsesResolvedServerAndSender()
    {
        $mail = (new TransportMailer(null, SmtpSettings::resolve($this->config, [
            'email.smtpHost' => 'mail.example.com',
            'email.fromEmail' => 'noreply@example.com',
            'email.fromName' => 'Acme PM',
        ])))->transport();

        $this->assertSame('smtp', $mail->Mailer);
        $this->assertSame('mail.example.com', $mail->Host);
        $this->assertSame(587, $mail->Port);
        $this->assertTrue($mail->SMTPAuth);
        $this->assertSame('env-user@example.com', $mail->Username);
        $this->assertSame('env-secret', $mail->Password);
        $this->assertSame('noreply@example.com', $mail->From);
        $this->assertSame('Acme PM', $mail->FromName);
        $this->assertSame(0, $mail->SMTPDebug);
    }

    public function testMailerTimeoutIsOptional()
    {
        $smtp = SmtpSettings::resolve(['host' => 'mail.example.com', 'port' => 25, 'username' => 'u@example.com'], []);

        $this->assertSame((new PHPMailer())->Timeout, (new TransportMailer(null, $smtp))->transport()->Timeout);
        $this->assertSame(5, (new TransportMailer(5, $smtp))->transport()->Timeout);
    }
}

/**
 * Exposes the PHPMailer transport Mailer builds, so its setup can be checked
 * without sending.
 */
class TransportMailer extends Mailer
{
    public function transport()
    {
        return $this->createTransport();
    }
}
