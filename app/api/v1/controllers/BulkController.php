<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\Http\BulkExecutor;
use Gaia\Core\MVC\Http\BulkRequestValidator;
use Gaia\Core\MVC\Http\SubRequestRunner;
use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * Bulk Controller
 *
 * `POST /api/v1/bulk` runs a batch of ordinary REST calls in one HTTP request
 * and returns one result per call, in request order. Nothing is stored. The
 * batch needs `bulk.create`; every call in it is then authenticated and
 * authorized on its own with the caller's token, exactly like a separate
 * call (see docs/architecture/bulk/decisions.md).
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BulkController extends RestController
{
    /**
     * Only create is exposed: a batch is posted, never read back.
     *
     * @var array $aclMap
     */
    protected $aclMap = array(
        'post' => array(
            'action' => 'create',
            'controllerAction' => 'postAction',
        ),
    );

    /**
     * Validate the batch, run its calls and return their results.
     *
     * @throws \Gaia\Exception\BadRequest When the batch fails validation; no call runs then
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        global $settings, $currentUser, $logger;

        $items = BulkRequestValidator::fromSettings($settings)->validate((string) $this->request->getRawBody());
        // Read the header the way the token check did: Apache may pass it only as REDIRECT_HTTP_AUTHORIZATION.
        $authorization = (string) $this->getOAuthRequest()->headers('AUTHORIZATION', '');
        $userId = isset($currentUser->id) ? (string) $currentUser->id : null;

        $executor = new BulkExecutor(new SubRequestRunner($this->getDI()), $logger ?? null);
        $responses = $executor->run($items, $authorization, $userId);

        $this->response->setStatusCode(200, 'OK');
        $this->response->setJsonContent(['responses' => $responses]);

        return $this->response;
    }
}
