<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\MVC\Models\Workspace;
use Phalcon\Di\Di;

use function Gaia\Libraries\Utils\create_guid;

/**
 * The rule that every project belongs to a workspace (Workspaces D-002): a
 * project created without one gets its own one-project workspace, a project
 * created into one needs that workspace to exist and `workspace.update`.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProjectWorkspace
{
    const NAME_LENGTH = 100;

    const UNKNOWN_WORKSPACE = 'The workspace does not exist.';

    const NOT_ALLOWED = 'You are not allowed to add projects to this workspace.';

    /**
     * Make sure a new project has a workspace: create one named after the
     * project, with the project's short code (D-012) and the project's manager
     * as its manager (D-022), when none is given, otherwise check the given one.
     *
     * @param  string|null $workspaceId
     * @param  string|null $projectName
     * @param  string|null $projectShortCode
     * @param  string|null $projectManager
     * @return string The workspace id for the project
     * @throws \Gaia\Exception\Access    The caller may not update workspaces
     * @throws \InvalidArgumentException The workspace does not exist, or it could not be created
     */
    public function resolveForNewProject($workspaceId, $projectName, $projectShortCode = null, $projectManager = null)
    {
        if ($workspaceId === null || $workspaceId === '') {
            return $this->createFor((string) $projectName, (string) $projectShortCode, $projectManager);
        }

        $workspace = Workspace::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => $workspaceId],
        ]);
        if (!$workspace) {
            throw new \InvalidArgumentException(self::UNKNOWN_WORKSPACE);
        }
        if (!$this->mayUpdateWorkspaces()) {
            throw new \Gaia\Exception\Access(self::NOT_ALLOWED);
        }

        return (string) $workspace->id;
    }

    /**
     * Whether a save changes the workspace the project is stored in.
     *
     * @param  array|null $snapshot    The stored row, null for a new record
     * @param  mixed      $workspaceId The value being saved
     * @return bool
     */
    public function isMove($snapshot, $workspaceId)
    {
        if (!is_array($snapshot)) {
            return false;
        }

        return (string) ($snapshot['workspaceId'] ?? '') !== (string) $workspaceId;
    }

    /**
     * When the project joined the workspace it is saved in (Workspaces D-016): now for a new project or a move,
     * otherwise the stored value, so no request can write it.
     *
     * @param  array|null $snapshot    The stored row, null for a new record
     * @param  mixed      $workspaceId The value being saved
     * @param  string     $now         UTC datetime `Y-m-d H:i:s`
     * @return string|null
     */
    public function joinedAt($snapshot, $workspaceId, $now)
    {
        if (!is_array($snapshot) || $this->isMove($snapshot, $workspaceId)) {
            return $now;
        }

        return $snapshot['workspaceJoinedAt'] ?? null;
    }

    /**
     * The workspace takes the project's short code, or the first free code
     * derived from it when another workspace already has it. The project's
     * manager manages it; without one, the creator does (D-022).
     *
     * @param  string      $projectName
     * @param  string      $projectShortCode
     * @param  string|null $projectManager
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function createFor($projectName, $projectShortCode, $projectManager = null)
    {
        $workspace = new Workspace();
        $workspace->id = create_guid();
        $workspace->name = mb_substr($projectName, 0, self::NAME_LENGTH);
        $workspace->shortCode = (new WorkspaceShortCode())->freeCodeFrom($projectShortCode);
        $workspace->managerId = $this->managerFrom($projectManager);
        if (!$workspace->save()) {
            $messages = [];
            foreach ($workspace->getMessages() as $message) {
                $messages[] = $message->getMessage();
            }
            throw new \InvalidArgumentException(implode(' ', $messages));
        }

        return (string) $workspace->id;
    }

    /**
     * The project's manager when it is a live user, otherwise null so the
     * workspace falls back to its creator: a project's manager is not
     * checked, and must not make the project's own workspace fail.
     *
     * @param  mixed $projectManager
     * @return string|null
     */
    protected function managerFrom($projectManager)
    {
        return $this->managerRule()->isLiveUser($projectManager) ? (string) $projectManager : null;
    }

    /**
     * The rule a workspace's manager is checked against.
     *
     * @return WorkspaceManager
     */
    protected function managerRule()
    {
        return new WorkspaceManager();
    }

    /**
     * Whether the request's ACL allows `workspace.update`. Outside a request
     * (no ACL loaded: CLI, workflows run by the system) the check is skipped.
     *
     * @return bool
     */
    protected function mayUpdateWorkspaces()
    {
        $di = Di::getDefault();
        if ($di === null || !$di->has('acl')) {
            return true;
        }

        return $di->get('acl')->isModelActionAllowed('Workspace', 'update');
    }
}
