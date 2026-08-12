<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Security\AclLockoutGuard;

/**
 * Roles Controller
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RoleController extends RestController
{
    /**
     * Seed full ACL catalog on role create (RoleComponent::afterCreate).
     *
     * @var array
     */
    public $uses = ['Role'];

    /**
     * Project authorization flag
     * @var bool
     */
    protected $projectAuthorization = false;

    /**
     * System level flag
     * @var bool
     */
    protected $systemLevel = true;

    /**
     * Create a role and materialize its permission catalog atomically.
     *
     * Role + seed share one DB transaction: a failed seed rolls the role back.
     *
     * @method postAction
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        $db = $this->di->get('db');
        $db->begin();

        try {
            $response = parent::postAction();
            $statusCode = (int) $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $db->commit();
            } else {
                $db->rollback();
            }

            return $response;
        } catch (\Throwable $e) {
            try {
                $db->rollback();
            } catch (\Throwable $ignored) {
                // No open transaction (or already rolled back).
            }
            throw $e;
        }
    }

    /**
     * Reject deleting a role when no other role would remain able to
     * administer permissions, roles, role assignments, and users (see
     * `AclLockoutGuard`). Any role may otherwise be deleted, including
     * one named "Admin" — capability, not name, decides.
     *
     * @method deleteAction
     * @throws \Gaia\Exception\Permission
     * @return \Phalcon\Http\Response|null
     */
    public function deleteAction()
    {
        if (!AclLockoutGuard::systemRetainsAdminPath(['excludeRoleId' => $this->id])) {
            throw new \Gaia\Exception\Permission(
                "This role cannot be deleted: no other role would retain permission, role, userrole, and user write access.",
                null,
                null,
                [
                    'suggestion' => 'Grant full permission, role, userrole, and user write access to another role with at least one active assigned user before deleting this role.'
                ]
            );
        }

        return parent::deleteAction();
    }
}
