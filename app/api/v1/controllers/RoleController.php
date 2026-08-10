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
