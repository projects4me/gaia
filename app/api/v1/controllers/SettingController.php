<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Events\Notification\Mailer;
use Gaia\Libraries\Utils\SecureSettingMasker;
use Gaia\Libraries\Utils\Util;
use PHPMailer\PHPMailer\Exception as MailerException;

/**
 * Setting controller
 *
 * Application settings can only be read and updated; rows are seeded
 * (db/seeds/settings.sql). The aclMap below is what puts setting.get and
 * setting.update in the ACL catalog, so the Roles screen can grant them.
 * Secure values are masked on the way out and cannot be exported.
 * POST /setting/testemail sends a test message with the current SMTP settings.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SettingController extends RestController
{
    /**
     * Seconds the test email waits for the SMTP server (PHPMailer defaults to 300).
     *
     * @var int
     */
    public const TEST_EMAIL_TIMEOUT = 15;

    /**
     * Settings are system wide, not scoped to a project.
     *
     * @var bool $systemLevel
     */
    protected $systemLevel = true;

    /**
     * Only read and update; no create, put or delete.
     *
     * @var array $aclMap
     */
    protected $aclMap = array(
        'get' => array(
            'action' => 'get',
            'controllerAction' => 'getAction',
        ),
        'list' => array(
            'action' => 'get',
            'controllerAction' => 'listAction',
        ),
        'related' => array(
            'action' => 'get',
            'controllerAction' => 'relatedAction',
        ),
        'patch' => array(
            'action' => 'update',
            'controllerAction' => 'patchAction',
        ),
        // POST /api/v1/setting/testemail (app route in core/config/routes.php)
        'testemail' => array(
            'action' => 'update',
            'controllerAction' => 'testemailAction',
        ),
    );

    /**
     * Settings can't be exported (exports bypass the secure-value masking).
     *
     * @return \Phalcon\Http\Response
     */
    public function listAction()
    {
        if (filter_var($this->request->get('export', null, false), FILTER_VALIDATE_BOOLEAN)) {
            $this->response->setStatusCode(400, 'Bad Request');
            $this->response->setJsonContent(array(
                'status' => 'ERROR',
                'messages' => array('Settings cannot be exported.')
            ));
            return $this->response;
        }

        return parent::listAction();
    }

    /**
     * Send a test email with the current SMTP settings.
     *
     * Body: `{"recipient": "a@example.com"}` (or JSON:API `data.attributes.recipient`).
     * Returns 200 when the SMTP server accepted the message, 422 for a missing
     * or invalid recipient, and 502 with the SMTP error otherwise; a failure is
     * never reported as success.
     *
     * @return \Phalcon\Http\Response
     */
    public function testemailAction()
    {
        $util = new Util();
        $body = $util->objectToArray($this->request->getJsonRawBody());
        $recipient = isset($body['data']['attributes']['recipient'])
            ? $body['data']['attributes']['recipient']
            : (isset($body['recipient']) ? $body['recipient'] : null);
        $recipient = is_string($recipient) ? trim($recipient) : '';

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->response->setStatusCode(422, 'Unprocessable Entity');
            $this->response->setJsonContent([
                'status' => 'ERROR',
                'messages' => ['A valid recipient email address is required.'],
            ]);
            return $this->response;
        }

        try {
            $mail = Mailer::fromSettings(true);
            $mail->Timeout = self::TEST_EMAIL_TIMEOUT;
            $mail->addAddress($recipient);
            $mail->Subject = 'Projects4Me test email';
            $mail->Body = 'This is a test email from Projects4Me. Your email settings work.';
            $mail->send();
        } catch (\Exception $e) {
            $error = ($e instanceof MailerException && isset($mail) && $mail->ErrorInfo !== '')
                ? $mail->ErrorInfo
                : $e->getMessage();
            $this->response->setStatusCode(502, 'Bad Gateway');
            $this->response->setJsonContent([
                'status' => 'ERROR',
                'messages' => ['The test email could not be sent: ' . $error],
            ]);
            return $this->response;
        }

        $this->response->setStatusCode(200, 'OK');
        $this->response->setJsonContent([
            'status' => 'OK',
            'messages' => ["Test email sent to {$recipient}."],
        ]);
        return $this->response;
    }

    /**
     * Mask secure settings in every response this controller sends.
     *
     * @param  array $data
     * @return \Phalcon\Http\Response
     */
    protected function returnResponse(array $data)
    {
        return parent::returnResponse(SecureSettingMasker::maskDocument($data));
    }
}
