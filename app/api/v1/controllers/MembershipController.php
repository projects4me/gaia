<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Workspaces\MemberRemoval;
use Gaia\Libraries\Workspaces\MemberRemovalRefusal;
use Gaia\MVC\Models\Membership;

/**
 * Memberships Controller
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MembershipController extends RestController
{
    /**
     * Project authorization flag
     *
     * @var bool
     */
    protected $projectAuthorization = false;

    /**
     * System level flag
     *
     * @var bool
     */
    protected $systemLevel = true;

    /**
     * Components that this controller uses.
     *
     * @var  $uses
     * @type array
     */
    public $uses = array('ProjectActivities', 'Notification', 'Events\\MembershipLiveEvents');

    /**
     * Delete action: hands over the member's open issues in the workspace (query
     * parameter handoverTo: a user id) and deletes the membership in one
     * transaction. 409 when open issues need a handover, 422 for a wrong target.
     *
     * @return \Phalcon\Http\Response
     */
    public function deleteAction()
    {
        $membership = Membership::findFirst(['conditions' => 'id = :id:', 'bind' => ['id' => $this->id]]);
        if (!$membership) {
            return parent::deleteAction();
        }

        $db = $this->getDI()->getShared('db');
        $db->begin();
        try {
            (new MemberRemoval())->handOver($membership, $this->request->get('handoverTo'));
            $response = parent::deleteAction();
            if ($response->getStatusCode() >= 400) {
                $db->rollback();
            } else {
                $db->commit();
            }

            return $response;
        } catch (MemberRemovalRefusal $refusal) {
            $db->rollback();
            $this->response->setStatusCode($refusal->getCode());
            $this->response->setJsonContent(['status' => 'ERROR', 'messages' => [$refusal->getMessage()]]);

            return $this->response;
        } catch (\Throwable $e) {
            try {
                $db->rollback();
            } catch (\Throwable $ignored) {
                // No open transaction.
            }
            throw $e;
        }
    }
}
