<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Agents;

use Gaia\Libraries\Security\AclLockoutGuard;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\User;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Keeps an agent's users row and agents row together (Class Table
 * Inheritance): both are created in one transaction, deleting either one
 * deletes the other (unless that would lock out ACL administration), and the
 * user's name follows the agent's name.
 *
 * An agent user has no usable password: it is created with a random secret
 * that is hashed and never revealed.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Agents
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentLifecycle
{
    /**
     * Attributes of a create payload that belong to the users row.
     *
     * @var array
     */
    public const USER_FIELDS = ['email', 'accountStatus'];

    /**
     * accountStatus of a new agent when the payload doesn't set one.
     *
     * @var string
     */
    public const DEFAULT_ACCOUNT_STATUS = 'Active';

    /**
     * Agent attributes the caller may not set on create.
     *
     * @var array
     */
    private const SERVER_FIELDS = [
        'id', 'userId', 'deleted', 'createdUser', 'createdUserName',
        'modifiedUser', 'modifiedUserName', 'dateCreated', 'dateModified',
    ];

    /**
     * Create the users row (type Agent) and the agents row in one transaction.
     *
     * @param  array $attributes Agent fields plus `email` and optional `accountStatus`
     * @return Agent
     * @throws \Gaia\Exception\Permission When the payload is invalid
     */
    public function create(array $attributes)
    {
        $name = isset($attributes['name']) ? trim((string) $attributes['name']) : '';
        if ($name === '') {
            $this->reject('An agent needs a name.');
        }

        $email = isset($attributes['email']) ? trim((string) $attributes['email']) : '';
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->reject('An agent needs a valid email.');
        }
        if (User::findFirstByEmail($email)) {
            $this->reject('A user with this email already exists.');
        }

        $this->assertSponsorExists(isset($attributes['sponsorId']) ? $attributes['sponsorId'] : null);

        $accountStatus = isset($attributes['accountStatus']) && (string) $attributes['accountStatus'] !== ''
            ? (string) $attributes['accountStatus']
            : self::DEFAULT_ACCOUNT_STATUS;

        return $this->transactional(function () use ($attributes, $name, $email, $accountStatus) {
            $user = new User();
            $user->id = create_guid();
            $user->name = $name;
            $user->email = $email;
            $user->type = User::TYPE_AGENT;
            $user->accountStatus = $accountStatus;
            // Hashed by encryptPasswordBehavior and never revealed: no usable password.
            $user->password = bin2hex(random_bytes(32));
            $this->save($user);

            $agent = new Agent();
            $agent->assign($this->agentAttributes($attributes));
            $agent->id = create_guid();
            $agent->userId = $user->id;
            $agent->name = $name;
            $this->save($agent);

            return $agent;
        });
    }

    /**
     * Soft-delete the users row of a deleted agent.
     *
     * @param  string $userId
     * @return void
     */
    public function deleteUser($userId)
    {
        $user = $this->findLiveUser($userId);
        if ($user && !$user->delete()) {
            $this->reject('The user of this agent could not be deleted.');
        }
    }

    /**
     * Soft-delete the agents row of a deleted agent user.
     *
     * @param  string $userId
     * @return void
     */
    public function deleteAgentOfUser($userId)
    {
        $agent = Agent::findForUser($userId);
        if ($agent && !$agent->delete()) {
            $this->reject('The agent of this user could not be deleted.');
        }
    }

    /**
     * Copy the agent's name to its users row.
     *
     * @param  Agent $agent
     * @return void
     */
    public function syncUserName(Agent $agent)
    {
        $user = $this->findLiveUser($agent->userId);
        if (!$user || (string) $user->name === (string) $agent->name) {
            return;
        }

        $user->name = $agent->name;
        $this->save($user);
    }

    /**
     * Reject a sponsor that is not an existing user.
     *
     * @param  mixed $sponsorId
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    public function assertSponsorExists($sponsorId)
    {
        if ($sponsorId === null || (string) $sponsorId === '') {
            return;
        }

        if (!$this->findLiveUser($sponsorId)) {
            $this->reject('The sponsor of an agent must be an existing user.');
        }
    }

    /**
     * Reject deleting an agent whose user is the last active member of the
     * last role able to administer ACL (see `AclLockoutGuard`).
     *
     * @param  Agent $agent
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    public function assertDeletable(Agent $agent)
    {
        if (AclLockoutGuard::systemRetainsAdminPath(['excludeUserId' => (string) $agent->userId])) {
            return;
        }

        throw new \Gaia\Exception\Permission(
            "This agent cannot be deleted: its user is the last active member of the last role able to administer permissions, roles, role assignments, and users.",
            null,
            null,
            [
                'suggestion' => 'Assign another active user to a role with full permission, role, userrole, and user write access before deleting this agent.'
            ]
        );
    }

    /**
     * Run the paired writes in one database transaction.
     *
     * @param  callable $work
     * @return mixed What $work returns
     * @throws \Throwable Whatever $work throws, after rolling back
     */
    public function transactional(callable $work)
    {
        $db = \Phalcon\Di::getDefault()->getShared('db');
        $db->begin();

        try {
            $result = $work();
            $db->commit();
            return $result;
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
     * A user that is not soft-deleted.
     *
     * @param  mixed $userId
     * @return User|null
     */
    private function findLiveUser($userId)
    {
        $user = User::findFirst([
            'conditions' => 'id = :id: AND deleted = 0',
            'bind' => ['id' => (string) $userId],
        ]);

        return $user ?: null;
    }

    /**
     * The payload without users-row and server-managed fields.
     *
     * @param  array $attributes
     * @return array
     */
    private function agentAttributes(array $attributes)
    {
        $excluded = array_merge(self::USER_FIELDS, self::SERVER_FIELDS);

        return array_diff_key($attributes, array_flip($excluded));
    }

    /**
     * Save a model or reject with its validation messages.
     *
     * @param  \Phalcon\Mvc\Model $model
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    private function save($model)
    {
        if ($model->save()) {
            return;
        }

        $messages = [];
        foreach ($model->getMessages() as $message) {
            $messages[] = $message->getMessage();
        }
        $this->reject(implode(' ', $messages) ?: 'The agent could not be saved.');
    }

    /**
     * @param  string $message
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    private function reject($message)
    {
        throw new \Gaia\Exception\Permission($message);
    }
}
