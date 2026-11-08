<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

use Gaia\Libraries\Security\AclLockoutGuard;
use Gaia\Libraries\Security\LiveAccessTokens;
use Gaia\Libraries\Security\ServiceClientGuard;
use Gaia\Libraries\Tools\AgentToolGrants;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Oauthclient;
use Gaia\MVC\Models\Project;
use Gaia\MVC\Models\Serviceclient;
use Gaia\MVC\Models\User;

/**
 * Introspects any bearer token (RFC 7662 names plus Gaia's) for services
 * such as Hermes (D-065).
 *
 * The token's actor is a `user`, an `agent` or a `service`:
 * - a token with a user is the user's, or the agent's when the user is an
 *   agent; it carries the user's project ids (memberships), and an agent's
 *   carries the agent and its tool grants as agaia-4029 built them (an
 *   agent's token must come from the agent's own client);
 * - a token with a user issued to a `service` client is delegated: the
 *   service acts for the agent (`act` is the service's client id), so the
 *   user must be a live agent;
 * - a token without a user is a service client's own token.
 *
 * Every active response has the same keys. A missing, expired or revoked
 * token, a disabled or deleted user, agent or service client, and anything
 * else is `{"active": false}` with no detail.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Library
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TokenIntrospection
{
    /**
     * Actor types.
     */
    public const ACTOR_USER = 'user';
    public const ACTOR_AGENT = 'agent';
    public const ACTOR_SERVICE = 'service';

    /**
     * @var LiveAccessTokens
     */
    private $tokens;

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
        $this->tokens = new LiveAccessTokens($storage);
        $this->grants = $grants ?: new AgentToolGrants();
    }

    /**
     * @param  mixed $token The bearer token to introspect
     * @return array
     */
    public function introspect($token)
    {
        $data = $this->tokens->find($token);
        if (!$data || empty($data['client_id'])) {
            return $this->inactive();
        }

        $clientId = (string) $data['client_id'];
        $email = isset($data['user_id']) ? (string) $data['user_id'] : '';
        $kind = ClientKind::of($clientId);
        $service = $kind === ClientKind::SERVICE ? $this->liveServiceClient($clientId) : null;
        if ($kind === null || ($kind === ClientKind::SERVICE && !$service)) {
            return $this->inactive();
        }

        if ($email === '') {
            return $service ? $this->serviceResponse($data, $service) : $this->inactive();
        }

        return $this->userResponse($data, $email, $service);
    }

    /**
     * A service client's own token.
     *
     * @param  array         $data
     * @param  Serviceclient $service
     * @return array
     */
    private function serviceResponse(array $data, Serviceclient $service)
    {
        return $this->active($data, [
            'sub' => null,
            'actorType' => self::ACTOR_SERVICE,
            'act' => null,
            'projectIds' => [],
            'serviceKind' => (string) $service->kind,
        ]);
    }

    /**
     * A user's or an agent's token, delegated when a service client holds it.
     *
     * @param  array              $data
     * @param  string             $email
     * @param  Serviceclient|null $service The acting service, for a delegated token
     * @return array
     */
    private function userResponse(array $data, $email, $service)
    {
        $user = $this->findLiveUser($email);
        if (!$user) {
            return $this->inactive();
        }

        $agent = $user->isAgent() ? $this->findLiveAgent($user->id) : null;
        if (($user->isAgent() || $service) && !$agent) {
            return $this->inactive();
        }
        if ($agent && !$service && !$this->isOwnClient($data['client_id'], $user->id)) {
            return $this->inactive();
        }

        $response = $this->active($data, [
            'sub' => $user->id,
            'actorType' => $agent ? self::ACTOR_AGENT : self::ACTOR_USER,
            'act' => $service ? (string) $data['client_id'] : null,
            'projectIds' => Project::accessibleIdsForUser($user->id),
            'serviceKind' => $service ? (string) $service->kind : null,
        ]);

        if ($agent) {
            $response['agent'] = [
                'id' => $agent->id,
                'name' => $agent->name,
                'userId' => $agent->userId,
                'sponsorId' => $agent->sponsorId,
            ];
            $response['toolGrants'] = $this->grants->forAgent($agent->id);
        }

        return $response;
    }

    /**
     * The fields every active response has, then the actor's.
     *
     * @param  array $data
     * @param  array $actor
     * @return array
     */
    private function active(array $data, array $actor)
    {
        return [
            'active' => true,
            'token_type' => 'Bearer',
            'clientId' => (string) $data['client_id'],
            'scope' => isset($data['scope']) ? $data['scope'] : null,
            'exp' => empty($data['expires']) ? null : (int) $data['expires'],
        ] + $actor;
    }

    /**
     * @return array
     */
    private function inactive()
    {
        return ['active' => false];
    }

    /**
     * The active service client of a `service` client, or null.
     *
     * @param  string $clientId
     * @return Serviceclient|null
     */
    private function liveServiceClient($clientId)
    {
        try {
            return ServiceClientGuard::assertClientCredentialsAllowed($clientId);
        } catch (\Gaia\Exception\UnAuthorized $e) {
            return null;
        }
    }

    /**
     * The live user the token was issued to (tokens key users by email).
     *
     * @param  string $email
     * @return User|null
     */
    private function findLiveUser($email)
    {
        $user = User::findFirst([
            'conditions' => 'email = :email: AND deleted = 0',
            'bind' => ['email' => $email],
        ]);

        if (!$user || !AclLockoutGuard::isAuthenticatableAccountStatus($user->accountStatus)) {
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
     * Whether the client is bound to the user (an agent's own client).
     *
     * @param  string $clientId
     * @param  string $userId
     * @return bool
     */
    private function isOwnClient($clientId, $userId)
    {
        return (bool) Oauthclient::findFirst([
            'conditions' => 'client_id = :clientId: AND user_id = :userId:',
            'bind' => ['clientId' => (string) $clientId, 'userId' => (string) $userId],
        ]);
    }
}
