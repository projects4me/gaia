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
     * project when none is given, otherwise check the given one.
     *
     * @param  string|null $workspaceId
     * @param  string|null $projectName
     * @return string The workspace id for the project
     * @throws \Gaia\Exception\Access    The caller may not update workspaces
     * @throws \InvalidArgumentException The workspace does not exist, or it could not be created
     */
    public function resolveForNewProject($workspaceId, $projectName)
    {
        if ($workspaceId === null || $workspaceId === '') {
            return $this->createFor((string) $projectName);
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
     * @param  string $projectName
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function createFor($projectName)
    {
        $workspace = new Workspace();
        $workspace->id = create_guid();
        $workspace->name = mb_substr($projectName, 0, self::NAME_LENGTH);
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
