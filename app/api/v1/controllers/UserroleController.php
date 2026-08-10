<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Security\AclLockoutGuard;
use Gaia\MVC\Models\Userrole;

/**
 * UserRole Controller
 *
 * Manages application-wide role assignments for users.
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class UserroleController extends RestController
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
     * Reject removing the last membership of the last role that can
     * administer permissions, roles, role assignments, and users (see
     * `AclLockoutGuard`).
     *
     * @method deleteAction
     * @throws \Gaia\Exception\Permission
     * @return \Phalcon\Http\Response|null
     */
    public function deleteAction()
    {
        $userrole = Userrole::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => $this->id],
        ]);

        if ($userrole) {
            $stillAdministrable = AclLockoutGuard::systemRetainsAdminPath([
                'excludeUserroleId' => $userrole->id,
                'affectedRoleIdForMembership' => $userrole->roleId,
            ]);

            if (!$stillAdministrable) {
                throw new \Gaia\Exception\Permission(
                    "This role assignment cannot be removed: it is the last active membership of the last role able to administer permissions, roles, role assignments, and users.",
                    null,
                    null,
                    [
                        'suggestion' => 'Assign another active user to a role with full permission, role, userrole, and user write access before removing this membership.'
                    ]
                );
            }
        }

        return parent::deleteAction();
    }
}
