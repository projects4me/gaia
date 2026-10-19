<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Events\Notification\Mailer;
use Gaia\Events\Notification\MailerErrorMapper;
use Gaia\Events\Notification\TestEmailSender;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

/**
 * The settings test email (agaia-4017): each PHPMailer failure maps to one
 * result code, and the sender checks the recipient before connecting.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TestEmailSenderTest extends TestCase
{
    /**
     * @dataProvider phpMailerErrors
     */
    public function testMapsEachPhpMailerErrorToItsCode($languageKey, $suffix, $code)
    {
        $messages = (new PHPMailer())->getTranslations();

        $this->assertSame($code, (new MailerErrorMapper())->toCode($messages[$languageKey] . $suffix));
    }

    public function phpMailerErrors()
    {
        return [
            'authentication' => ['authenticate', '', MailerErrorMapper::SMTP_AUTH_FAILED],
            'host unreachable or TLS failed' => [
                'connect_host',
                ' Connection refused',
                MailerErrorMapper::SMTP_CONNECTION_FAILED,
            ],
            'connect failed' => [
                'smtp_connect_failed',
                ' https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting',
                MailerErrorMapper::SMTP_CONNECTION_FAILED,
            ],
            'recipient rejected' => ['recipients_failed', 'to@example.com', MailerErrorMapper::UNKNOWN_ERROR],
            'data not accepted' => ['data_not_accepted', '', MailerErrorMapper::UNKNOWN_ERROR],
        ];
    }

    public function testUnrecognisedOrEmptyErrorIsUnknown()
    {
        $mapper = new MailerErrorMapper();

        $this->assertSame(MailerErrorMapper::UNKNOWN_ERROR, $mapper->toCode('Something else broke'));
        $this->assertSame(MailerErrorMapper::UNKNOWN_ERROR, $mapper->toCode(''));
    }

    public function testRealConnectionFailureMapsToConnectionFailed()
    {
        // Nothing listens on port 1: the connection is refused at once.
        $mailer = new Mailer(2, [
            'host' => '127.0.0.1',
            'port' => 1,
            'encryption' => 'none',
            'auth' => true,
            'username' => 'user@example.com',
            'password' => 'secret',
            'fromName' => 'Projects4Me',
            'fromEmail' => 'user@example.com',
        ]);

        $this->assertFalse($mailer->send('to@example.com', '', 'Subject', '<p>Hi</p>', 'Hi'));
        $this->assertSame(MailerErrorMapper::SMTP_CONNECTION_FAILED, (new MailerErrorMapper())->toCode($mailer->getError()));
    }

    /**
     * @dataProvider invalidRecipients
     */
    public function testInvalidRecipientIsRejectedBeforeConnecting($recipient)
    {
        $sender = new RecordingTestEmailSender(true);

        $this->assertSame(TestEmailSender::INVALID_RECIPIENT, $sender->send($recipient, []));
        $this->assertNull($sender->mailer);
    }

    public function invalidRecipients()
    {
        return [
            'missing' => [null],
            'blank' => ['  '],
            'not an address' => ['admin'],
            'not a string' => [['admin@example.com']],
        ];
    }

    public function testSentMessageGoesToTheRecipientWithTheGivenSettings()
    {
        $sender = new RecordingTestEmailSender(true);
        $smtp = ['host' => 'mail.example.com'];

        $this->assertSame(TestEmailSender::TEST_EMAIL_SENT, $sender->send(' admin@example.com ', $smtp));
        $this->assertSame($smtp, $sender->smtp);
        $this->assertSame('admin@example.com', $sender->mailer->sent[0]['toEmail']);
    }

    public function testFailedSendReturnsTheMappedCode()
    {
        $messages = (new PHPMailer())->getTranslations();
        $sender = new RecordingTestEmailSender(false, $messages['authenticate']);

        $this->assertSame(MailerErrorMapper::SMTP_AUTH_FAILED, $sender->send('admin@example.com', []));
    }
}

/**
 * TestEmailSender whose Mailer records the message instead of sending it.
 */
class RecordingTestEmailSender extends TestEmailSender
{
    /** @var RecordingMailer|null */
    public $mailer;

    /** @var array|null */
    public $smtp;

    /** @var bool */
    private $result;

    /** @var string */
    private $error;

    public function __construct($result, $error = '')
    {
        parent::__construct();
        $this->result = $result;
        $this->error = $error;
    }

    protected function createMailer(array $smtp)
    {
        $this->smtp = $smtp;
        $this->mailer = new RecordingMailer($this->result, $this->error);
        return $this->mailer;
    }
}

/**
 * Mailer that records messages and returns a fixed result.
 */
class RecordingMailer extends Mailer
{
    /** @var array */
    public $sent = [];

    /** @var bool */
    private $result;

    public function __construct($result, $error)
    {
        $this->result = $result;
        $this->error = $result ? '' : $error;
    }

    public function send($toEmail, $toName, $subject, $htmlBody, $altBody)
    {
        $this->sent[] = compact('toEmail', 'toName', 'subject', 'htmlBody', 'altBody');
        return $this->result;
    }
}
