<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Membership;
use Gaia\MVC\Models\Role;
use Gaia\MVC\Models\Userrole;

/**
 * Reads the users a workflow may pick in a project: its members, the
 * members who hold a role, and an agent's sponsor.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProjectMembers
{
    /**
     * Whether the user is a member of the project.
     *
     * @param  string $projectId
     * @param  string $userId
     * @return bool
     */
    public function isMember($projectId, $userId)
    {
        return Membership::findFirst([
            'conditions' => 'projectId = :projectId: AND userId = :userId:',
            'bind' => ['projectId' => (string) $projectId, 'userId' => (string) $userId],
        ]) !== false;
    }

    /**
     * The longest-standing member of the project who holds the role, named
     * by its id or its name.
     *
     * @param  string $projectId
     * @param  string $role
     * @return string|null The user id.
     */
    public function firstWithRole($projectId, $role)
    {
        $row = \Phalcon\Di::getDefault()->get('modelsManager')->createBuilder()
            ->columns(['Membership.userId as userId'])
            ->from(['Membership' => Membership::class])
            ->innerJoin(Userrole::class, 'Userrole.userId = Membership.userId AND Userrole.deleted = false', 'Userrole')
            ->innerJoin(Role::class, 'Role.id = Userrole.roleId AND Role.deleted = false', 'Role')
            ->where('Membership.projectId = :projectId:', ['projectId' => (string) $projectId])
            ->andWhere('(Role.id = :role: OR Role.name = :role:)', ['role' => (string) $role])
            ->orderBy('Membership.dateCreated, Membership.userId')
            ->limit(1)
            ->getQuery()
            ->getSingleResult();

        return $row ? (string) $row->userId : null;
    }

    /**
     * The sponsor of the agent that acts as the user.
     *
     * @param  string $userId The agent's user id.
     * @return string|null The sponsor's user id.
     */
    public function sponsorOf($userId)
    {
        $agent = Agent::findFirst([
            'conditions' => 'userId = :userId: AND deleted = false',
            'bind' => ['userId' => (string) $userId],
        ]);

        return $agent && $agent->sponsorId ? (string) $agent->sponsorId : null;
    }
}
