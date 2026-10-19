<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Events\Notification\MailerErrorMapper;
use Gaia\Events\Notification\TestEmailSender;
use Gaia\Libraries\Utils\SecureSettingMasker;
use Gaia\Libraries\Utils\SmtpSettings;

/**
 * Setting controller
 *
 * Application settings can only be read and updated; rows are seeded
 * (db/seeds/settings.sql). The aclMap below is what puts setting.get and
 * setting.update in the ACL catalog, so the Roles screen can grant them.
 * Secure values are masked on the way out and cannot be exported.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SettingController extends RestController
{
    /**
     * Settings are system wide, not scoped to a project.
     *
     * @var bool $systemLevel
     */
    protected $systemLevel = true;

    /**
     * HTTP status for each test email result.
     *
     * @var array
     */
    private const TEST_RESULT_STATUS = [
        TestEmailSender::TEST_EMAIL_SENT => 200,
        TestEmailSender::INVALID_RECIPIENT => 422,
        TestEmailSender::SMTP_PASSWORD_REQUIRED => 422,
        MailerErrorMapper::SMTP_AUTH_FAILED => 502,
        MailerErrorMapper::SMTP_CONNECTION_FAILED => 502,
        MailerErrorMapper::UNKNOWN_ERROR => 502,
    ];

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
        'patchCollection' => array(
            'action' => 'update',
            'controllerAction' => 'patchCollectionAction',
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
     * Save a collection of settings, or with `"test": true` send a test email
     * with the submitted email settings to `recipient` and save nothing.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchCollectionAction()
    {
        $data = $this->request->getJsonRawBody();
        if (!is_object($data) || !isset($data->test) || !filter_var($data->test, FILTER_VALIDATE_BOOLEAN)) {
            return parent::patchCollectionAction();
        }

        return $this->testEmailSettings($data);
    }

    /**
     * Send a test email with the submitted email settings, over the saved
     * settings and the smtp config. Runs the same per-record checks as a
     * save, but never saves, opens a transaction or fires rest:afterUpdate.
     * A test that changes the server or account must bring its own password.
     *
     * @param  object $data The request body
     * @return \Phalcon\Http\Response {"testResult": "<code>"}
     */
    protected function testEmailSettings($data)
    {
        if (!isset($data->collection) || !is_array($data->collection)) {
            return $this->returnResponse(array('error' => array('code' => 400, 'description' => 'Collection missing')));
        }

        $modelName = $this->modelName;
        $submitted = array();
        $secureNames = array();
        foreach ($data->collection as $index => $resource) {
            $temp = (array) $resource;
            if (!isset($temp['id'])) {
                return $this->returnResponse(array('error' => array('code' => 400, 'description' => 'Id missing for record ' . $index)));
            }

            $temp = $this->authorizeRecordUpdate($temp);
            $model = $modelName::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => (string) $temp['id']],
            ]);
            if (!isset($model->id)) {
                return $this->returnResponse(array('error' => array('code' => 400, 'description' => 'Invalid record with identifier ' . $temp['id'])));
            }
            if ($model->groupName !== 'email') {
                return $this->returnResponse(array('error' => array('code' => 400, 'description' => 'Only email settings can be tested')));
            }

            if (array_key_exists('settingValue', $temp)) {
                $submitted[(string) $model->name] = $temp['settingValue'];
            }
            if ((int) $model->secure === 1) {
                $secureNames[] = (string) $model->name;
            }
        }

        $smtp = SmtpSettings::loadWith($submitted, $secureNames);
        $recipient = isset($data->recipient) ? $data->recipient : null;
        $code = SmtpSettings::reusesStoredPassword(SmtpSettings::load(), $smtp, $submitted)
            ? TestEmailSender::SMTP_PASSWORD_REQUIRED
            : (new TestEmailSender())->send($recipient, $smtp);

        $this->response->setStatusCode(isset(self::TEST_RESULT_STATUS[$code]) ? self::TEST_RESULT_STATUS[$code] : 502);
        return $this->returnResponse(array('testResult' => $code));
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
