<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Agents;

use Gaia\Libraries\Oauth\Storage\Pdo;
use Gaia\Libraries\Security\AclLockoutGuard;
use Gaia\Libraries\Tools\AgentToolGrants;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Oauthclient;
use Gaia\MVC\Models\User;

/**
 * Introspects an agent's bearer token (RFC 7662 shaped) for Ares.
 *
 * A token is active when it exists, has not expired, was issued to an agent's
 * own client_credentials client, and the agent and its user are live and
 * allowed to sign in. Tokens of people, and anything else, are reported as
 * `{"active": false}` with no detail. An active token comes back with its
 * agent and the agent's tool grants (see `AgentToolGrants`), so Ares can
 * enforce the grants and then call Gaia with the token itself.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Library
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentTokenIntrospection
{
    /**
     * @var \OAuth2\Storage\AccessTokenInterface|null
     */
    private $storage;

    /**
     * @var AgentToolGrants
     */
    private $grants;

    /**
     * @param \OAuth2\Storage\AccessTokenInterface|null $storage Token storage; null uses Gaia's
     * @param AgentToolGrants|null                      $grants
     */
    public function __construct($storage = null, AgentToolGrants $grants = null)
    {
        $this->storage = $storage;
        $this->grants = $grants ?: new AgentToolGrants();
    }

    /**
     * @param  mixed $token The bearer token to introspect
     * @return array
     */
    public function introspect($token)
    {
        if (!is_string($token) || $token === '') {
            return $this->inactive();
        }

        $data = $this->storage()->getAccessToken($token);
        if (!$data || $this->isExpired($data)) {
            return $this->inactive();
        }

        $user = $this->findAgentUser(isset($data['user_id']) ? $data['user_id'] : null);
        $agent = $user ? $this->findLiveAgent($user->id) : null;
        if (!$agent || !$this->isAgentClient($data['client_id'], $user->id)) {
            return $this->inactive();
        }

        return [
            'active' => true,
            'token_type' => 'Bearer',
            'client_id' => $data['client_id'],
            'sub' => $user->id,
            'username' => $user->email,
            'exp' => (int) $data['expires'],
            'scope' => isset($data['scope']) ? $data['scope'] : null,
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'userId' => $agent->userId,
                'sponsorId' => $agent->sponsorId,
            ],
            'toolGrants' => $this->grants->forAgent($agent->id),
        ];
    }

    /**
     * @return array
     */
    private function inactive()
    {
        return ['active' => false];
    }

    /**
     * Same rule as the resource check: a token without an expiry never expires.
     *
     * @param  array $data
     * @return bool
     */
    private function isExpired(array $data)
    {
        return !empty($data['expires']) && time() > (int) $data['expires'];
    }

    /**
     * The live agent user the token was issued to (tokens key users by email).
     *
     * @param  mixed $email
     * @return User|null
     */
    private function findAgentUser($email)
    {
        if ($email === null || (string) $email === '') {
            return null;
        }

        $user = User::findFirst([
            'conditions' => 'email = :email: AND deleted = 0',
            'bind' => ['email' => (string) $email],
        ]);

        if (!$user || !$user->isAgent() || !AclLockoutGuard::isAuthenticatableAccountStatus($user->accountStatus)) {
            return null;
        }

        return $user;
    }

    /**
     * @param  string $userId
     * @return Agent|null
     */
    private function findLiveAgent($userId)
    {
        $agent = Agent::findFirst([
            'conditions' => 'userId = :userId: AND deleted = false',
            'bind' => ['userId' => (string) $userId],
        ]);

        return $agent ?: null;
    }

    /**
     * Whether the client is the agent's own client_credentials client.
     *
     * @param  string $clientId
     * @param  string $userId
     * @return bool
     */
    private function isAgentClient($clientId, $userId)
    {
        return (bool) Oauthclient::findFirst([
            'conditions' => 'client_id = :clientId: AND user_id = :userId: AND grant_types = :grantTypes:',
            'bind' => [
                'clientId' => (string) $clientId,
                'userId' => (string) $userId,
                'grantTypes' => AgentCredentials::GRANT_TYPES,
            ],
        ]);
    }

    /**
     * @return \OAuth2\Storage\AccessTokenInterface
     */
    private function storage()
    {
        if ($this->storage === null) {
            $this->storage = new Pdo(
                \Phalcon\Di::getDefault()->get('db')->getInternalHandler(),
                ['user_table' => 'users']
            );
        }

        return $this->storage;
    }
}
