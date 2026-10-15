<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Utils\SecureSettingMasker;

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
