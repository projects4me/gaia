<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Agenttool;
use Gaia\MVC\Models\Permission;
use Gaia\MVC\Models\User;
use Gaia\MVC\Models\Userrole;

/**
 * Stops an agent from raising its own privileges, whatever its ACL allows:
 * an agent user cannot change or delete its own agent (the agents row and its
 * users row), assign or remove its own roles, or change the permissions of a
 * role it holds, or change its own tool grants. Human users are not affected.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentEscalationGuard
{
    /**
     * Whether the acting user is an agent.
     *
     * @param  \Gaia\MVC\Models\User|null $actor
     * @return bool
     */
    public static function isAgent($actor)
    {
        return $actor instanceof User && $actor->isAgent();
    }

    /**
     * Deny an agent writing its own agents row.
     *
     * @param  \Gaia\MVC\Models\User|null $actor
     * @param  string|null $agentId
     * @return void
     * @throws \Gaia\Exception\Access
     */
    public static function assertNotOwnAgent($actor, $agentId)
    {
        if (!self::isAgent($actor) || $agentId === null || (string) $agentId === '') {
            return;
        }

        $agent = Agent::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $agentId],
        ]);
        if ($agent && (string) $agent->userId === (string) $actor->id) {
            self::deny('An agent cannot change or delete its own agent record.');
        }
    }

    /**
     * Deny an agent writing its own users row.
     *
     * @param  \Gaia\MVC\Models\User|null $actor
     * @param  string|null $userId
     * @return void
     * @throws \Gaia\Exception\Access
     */
    public static function assertNotOwnUser($actor, $userId)
    {
        if (self::isAgent($actor) && (string) $userId === (string) $actor->id) {
            self::deny('An agent cannot change or delete its own user record.');
        }
    }

    /**
     * Deny an agent writing a role assignment of its own: the assignment
     * being changed or deleted, or the user named in the payload.
     *
     * @param  \Gaia\MVC\Models\User|null $actor
     * @param  string|null $userroleId Existing assignment (update/delete)
     * @param  array       $attributes Write payload (create/update)
     * @return void
     * @throws \Gaia\Exception\Access
     */
    public static function assertNotOwnRoleAssignment($actor, $userroleId = null, array $attributes = [])
    {
        if (!self::isAgent($actor)) {
            return;
        }

        if (isset($attributes['userId']) && (string) $attributes['userId'] === (string) $actor->id) {
            self::deny('An agent cannot change its own roles.');
        }

        if ($userroleId === null || (string) $userroleId === '') {
            return;
        }

        $userrole = Userrole::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $userroleId],
        ]);
        if ($userrole && (string) $userrole->userId === (string) $actor->id) {
            self::deny('An agent cannot change its own roles.');
        }
    }

    /**
     * Deny an agent changing the permissions of a role it holds.
     *
     * @param  \Gaia\MVC\Models\User|null $actor
     * @param  string|null $roleId
     * @return void
     * @throws \Gaia\Exception\Access
     */
    public static function assertNotOwnRole($actor, $roleId)
    {
        if (!self::isAgent($actor) || $roleId === null || (string) $roleId === '') {
            return;
        }

        $held = Userrole::findFirst([
            'conditions' => 'userId = :userId: AND roleId = :roleId: AND deleted = false',
            'bind' => ['userId' => (string) $actor->id, 'roleId' => (string) $roleId],
        ]);
        if ($held) {
            self::deny('An agent cannot change the permissions of its own roles.');
        }
    }

    /**
     * Deny an agent writing a permission on a role it holds: the role in the
     * payload, or the role of the stored permission being changed.
     *
     * @param  \Gaia\MVC\Models\User|null $actor
     * @param  string|null $permissionId Existing permission (update/delete)
     * @param  string|null $roleId       Role in the write payload
     * @return void
     * @throws \Gaia\Exception\Access
     */
    public static function assertNotOwnPermission($actor, $permissionId, $roleId)
    {
        self::assertNotOwnRole($actor, $roleId);

        if (!self::isAgent($actor) || $permissionId === null || (string) $permissionId === '') {
            return;
        }

        $stored = Permission::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => (string) $permissionId],
        ]);
        if ($stored) {
            self::assertNotOwnRole($actor, $stored->roleId);
        }
    }

    /**
     * Deny an agent writing a tool grant of its own agent: the agent in the
     * payload, or the agent of the stored grant being changed or deleted.
     *
     * @param  \Gaia\MVC\Models\User|null $actor
     * @param  string|null $grantId Existing grant (update/delete)
     * @param  string|null $agentId Agent in the write payload
     * @return void
     * @throws \Gaia\Exception\Access
     */
    public static function assertNotOwnToolGrant($actor, $grantId, $agentId)
    {
        if (!self::isAgent($actor)) {
            return;
        }

        $agentIds = [$agentId];
        if ($grantId !== null && (string) $grantId !== '') {
            $stored = Agenttool::findFirst([
                'conditions' => 'id = :id: AND deleted = false',
                'bind' => ['id' => (string) $grantId],
            ]);
            if ($stored) {
                $agentIds[] = $stored->agentId;
            }
        }

        foreach ($agentIds as $id) {
            if ($id === null || (string) $id === '') {
                continue;
            }
            $agent = Agent::findFirst([
                'conditions' => 'id = :id: AND deleted = false',
                'bind' => ['id' => (string) $id],
            ]);
            if ($agent && (string) $agent->userId === (string) $actor->id) {
                self::deny('An agent cannot change its own tool grants.');
            }
        }
    }

    /**
     * @param  string $message
     * @return void
     * @throws \Gaia\Exception\Access
     */
    private static function deny($message)
    {
        throw new \Gaia\Exception\Access($message);
    }
}
