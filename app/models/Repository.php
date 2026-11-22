<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

/**
 * Repository Model
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Repository extends Model
{
    /**
     * Set while the repository moves to another workspace with its project
     * (Workspaces D-015); `repositoryBehavior` refuses any other workspace
     * change. Protected, with no `set` method, so a request body passed to
     * `assign()` cannot unlock the workspace.
     *
     * @var bool
     */
    protected $movingWithProject = false;

    /**
     * Lets the next save move this repository to its project's new workspace.
     *
     * @return void
     */
    public function moveWithProject()
    {
        $this->movingWithProject = true;
    }

    /**
     * @return bool whether this repository is moving with its project
     */
    public function isMovingWithProject()
    {
        return $this->movingWithProject;
    }
}
