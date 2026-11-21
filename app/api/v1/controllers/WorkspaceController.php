<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Workspaces\WorkspaceDeleteGate;
use Gaia\Libraries\Workspaces\WorkspaceDeleteRefusal;

/**
 * Workspace Controller
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceController extends RestController
{
    /**
     * Delete a workspace only when it owns nothing (Workspaces D-009).
     *
     * @return \Phalcon\Http\Response
     */
    public function deleteAction()
    {
        try {
            (new WorkspaceDeleteGate())->check($this->id);
        } catch (WorkspaceDeleteRefusal $refusal) {
            $this->response->setStatusCode($refusal->getCode());
            $this->response->setJsonContent([
                'status' => 'ERROR',
                'messages' => [$refusal->getMessage()],
                'counts' => $refusal->getCounts(),
            ]);

            return $this->response;
        }

        return parent::deleteAction();
    }
}
