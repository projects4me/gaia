<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Events\Notification\Mailer;
use Gaia\Libraries\Utils\SettingsCipher;
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

    public function testSubmittedValuesWinOverSavedOverConfig()
    {
        $saved = [
            'email.smtpHost' => 'saved.example.com',
            'email.smtpPort' => '2525',
            'email.smtpUsername' => 'saved-user@example.com',
            'email.smtpPassword' => 'saved-secret',
        ];
        $smtp = SmtpSettings::resolve($this->config, SmtpSettings::overlay($saved, [
            'email.smtpHost' => 'submitted.example.com',
            'email.smtpPassword' => 'submitted-secret',
        ], ['email.smtpPassword']));

        $this->assertSame('submitted.example.com', $smtp['host']);
        $this->assertSame('submitted-secret', $smtp['password']);
        // Not submitted: the saved value, then the config.
        $this->assertSame(2525, $smtp['port']);
        $this->assertSame('saved-user@example.com', $smtp['username']);
        $this->assertSame('Projects4me', $smtp['fromName']);
    }

    public function testSubmittedBlankClearsAPlainSettingLikeASave()
    {
        $smtp = SmtpSettings::resolve($this->config, SmtpSettings::overlay(
            ['email.smtpHost' => 'saved.example.com'],
            ['email.smtpHost' => ''],
            ['email.smtpPassword']
        ));

        $this->assertSame('smtp.gmail.com', $smtp['host']);
    }

    /**
     * @dataProvider keptSecrets
     */
    public function testBlankOrEncryptedPasswordKeepsTheSavedOne($submitted)
    {
        $smtp = SmtpSettings::resolve($this->config, SmtpSettings::overlay(
            ['email.smtpPassword' => 'saved-secret'],
            ['email.smtpPassword' => $submitted],
            ['email.smtpPassword']
        ));

        $this->assertSame('saved-secret', $smtp['password']);
    }

    public function keptSecrets()
    {
        return [
            'blank' => [''],
            'null' => [null],
            'ciphertext' => [SettingsCipher::PREFIX . 'not-decrypted'],
        ];
    }

    public function testBlankPasswordWithNothingSavedUsesTheConfig()
    {
        $smtp = SmtpSettings::resolve($this->config, SmtpSettings::overlay(
            [],
            ['email.smtpPassword' => ''],
            ['email.smtpPassword']
        ));

        $this->assertSame('env-secret', $smtp['password']);
    }

    /**
     * A test that points the stored password at another server or account
     * must bring its own password (PR #190 review).
     *
     * @dataProvider connectionChanges
     */
    public function testChangedConnectionWithoutAPasswordReusesTheStoredOne($submitted)
    {
        $saved = ['email.smtpHost' => 'smtp.example.com', 'email.smtpPassword' => 'saved-secret'];
        $stored = SmtpSettings::resolve($this->config, $saved);
        $resolved = SmtpSettings::resolve($this->config, SmtpSettings::overlay($saved, $submitted, ['email.smtpPassword']));

        $this->assertTrue(SmtpSettings::reusesStoredPassword($stored, $resolved, $submitted));
    }

    public function connectionChanges()
    {
        return [
            'host' => [['email.smtpHost' => 'attacker.example.com']],
            'port' => [['email.smtpPort' => '2525']],
            'encryption' => [['email.smtpEncryption' => 'none']],
            'username' => [['email.smtpUsername' => 'someone@example.com']],
            'host, blank password' => [['email.smtpHost' => 'attacker.example.com', 'email.smtpPassword' => '']],
            'host, ciphertext password' => [['email.smtpHost' => 'attacker.example.com', 'email.smtpPassword' => SettingsCipher::PREFIX . 'x']],
        ];
    }

    public function testChangedConnectionWithoutAPasswordReusesTheConfigOne()
    {
        $stored = SmtpSettings::resolve($this->config, []);
        $submitted = ['email.smtpHost' => 'attacker.example.com'];
        $resolved = SmtpSettings::resolve($this->config, SmtpSettings::overlay([], $submitted, ['email.smtpPassword']));

        $this->assertSame('env-secret', $resolved['password']);
        $this->assertTrue(SmtpSettings::reusesStoredPassword($stored, $resolved, $submitted));
    }

    public function testChangedConnectionWithANewPasswordIsAllowed()
    {
        $saved = ['email.smtpPassword' => 'saved-secret'];
        $stored = SmtpSettings::resolve($this->config, $saved);
        $submitted = ['email.smtpHost' => 'other.example.com', 'email.smtpPassword' => 'new-secret'];
        $resolved = SmtpSettings::resolve($this->config, SmtpSettings::overlay($saved, $submitted, ['email.smtpPassword']));

        $this->assertFalse(SmtpSettings::reusesStoredPassword($stored, $resolved, $submitted));
    }

    public function testUnchangedConnectionMayUseTheStoredPassword()
    {
        $saved = ['email.smtpHost' => 'smtp.example.com', 'email.smtpPassword' => 'saved-secret'];
        $stored = SmtpSettings::resolve($this->config, $saved);
        $submitted = ['email.smtpHost' => 'smtp.example.com', 'email.fromName' => 'Changed', 'email.smtpPassword' => ''];
        $resolved = SmtpSettings::resolve($this->config, SmtpSettings::overlay($saved, $submitted, ['email.smtpPassword']));

        $this->assertFalse(SmtpSettings::reusesStoredPassword($stored, $resolved, $submitted));
    }

    public function testNoAuthOrNoStoredPasswordHasNothingToReuse()
    {
        $submitted = ['email.smtpHost' => 'other.example.com'];

        $noAuth = array_merge($this->config, ['auth' => false]);
        $this->assertFalse(SmtpSettings::reusesStoredPassword(
            SmtpSettings::resolve($noAuth, []),
            SmtpSettings::resolve($noAuth, $submitted),
            $submitted
        ));

        $noPassword = array_merge($this->config, ['password' => null]);
        $this->assertFalse(SmtpSettings::reusesStoredPassword(
            SmtpSettings::resolve($noPassword, []),
            SmtpSettings::resolve($noPassword, $submitted),
            $submitted
        ));
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
