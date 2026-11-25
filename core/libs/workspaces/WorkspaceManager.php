<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\MVC\Models\User;

/**
 * The rule that a workspace has a manager (Workspaces D-022): a live user,
 * the creator unless another one is given.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceManager
{
    const NOT_A_USER = 'managerId must be the id of a user.';

    /**
     * The manager to save: the given one, or the creator when none is given.
     *
     * @param  mixed $managerId   The value being saved
     * @param  mixed $createdUser The workspace's creator
     * @return string|null
     */
    public function managerFor($managerId, $createdUser)
    {
        if ($managerId === null || (string) $managerId === '') {
            return ($createdUser === null || (string) $createdUser === '') ? null : (string) $createdUser;
        }

        return (string) $managerId;
    }

    /**
     * Whether the save sets or changes the manager, so it must be checked;
     * an unchanged manager (even one since deleted) is kept.
     *
     * @param  array|null $snapshot  The stored row, null for a new record
     * @param  mixed      $managerId The value being saved
     * @return bool
     */
    public function isChange($snapshot, $managerId)
    {
        $stored = is_array($snapshot) && isset($snapshot['managerId']) ? (string) $snapshot['managerId'] : '';

        return $stored !== (string) $managerId;
    }

    /**
     * Why the user cannot manage a workspace, or null when they can: the id
     * must name a live user. After managerFor() an empty value only occurs
     * when the workspace has no creator, which createdUserBehavior prevents.
     *
     * @param  mixed $managerId
     * @return string|null
     */
    public function managerError($managerId)
    {
        return $this->isLiveUser($managerId) ? null : self::NOT_A_USER;
    }

    /**
     * Whether the id names a live user, so they may manage a workspace.
     *
     * @param  mixed $id
     * @return bool
     */
    public function isLiveUser($id)
    {
        return $id !== null && (string) $id !== '' && $this->findUser((string) $id) !== null;
    }

    /**
     * The live user with the id, or null.
     *
     * @param  string $id
     * @return User|null
     */
    protected function findUser($id)
    {
        return User::findFirst([
            'conditions' => 'id = :id: AND deleted = 0',
            'bind' => ['id' => $id],
        ]) ?: null;
    }
}
