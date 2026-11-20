<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * Repository Controller
 *
 * Standard CRUD on a workspace's repositories, permissioned by the
 * `repository.*` actions. Creating or editing a repository also needs
 * `workspace.update`: it changes the workspace's environment, so that grant
 * comes on top of the repository action, never instead of it.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RepositoryController extends RestController
{
    /**
     * Create (or update through the id form) after the workspace check.
     *
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\Access
     */
    public function postAction()
    {
        $this->authorizeWorkspaceUpdate();

        return parent::postAction();
    }

    /**
     * Apply the standard record checks, then require `workspace.update`.
     *
     * @param  array $value Record payload including its id
     * @return array
     * @throws \Gaia\Exception\Access
     */
    protected function authorizeRecordUpdate(array $value)
    {
        $value = parent::authorizeRecordUpdate($value);
        $this->authorizeWorkspaceUpdate();

        return $value;
    }

    /**
     * Deny unless the current user may update workspaces.
     *
     * @return void
     * @throws \Gaia\Exception\Access
     */
    private function authorizeWorkspaceUpdate()
    {
        global $currentUser;

        if (!$this->getDI()->has('acl')) {
            return;
        }

        $this->getDI()->get('acl')->authorizeAction('workspace.update', $currentUser->id, 'Workspace');
    }
}
