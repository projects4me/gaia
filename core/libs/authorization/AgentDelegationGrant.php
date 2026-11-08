<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

use Gaia\Libraries\Agents\AgentDelegationScopes;
use Gaia\Libraries\Security\AgentDelegationGuard;
use OAuth2\GrantType\ClientCredentials;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use OAuth2\ResponseType\AccessTokenInterface;

/**
 * The agent-delegation grant (D-067): a runtime (a service client with the
 * `agent.delegate` scope) authenticates with its own client credentials and
 * gets a short-lived token acting as an agent, without holding the agent's
 * secret.
 *
 * Parameters: `agent_id` (the agents row) and an optional `scope`, which must
 * be covered by the agent's delegation scopes; without it the token gets all
 * of them. The token carries the agent user's email as user_id, the runtime's
 * client_id and the granted scope, and no refresh token. Its row is kept, so
 * the token table records which runtime acted for which agent and when.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentDelegationGrant extends ClientCredentials
{
    /**
     * The grant_type value of the grant.
     */
    public const GRANT_TYPE = 'urn:projects4me:params:oauth:grant-type:agent-delegation';

    /**
     * The agent user the token acts as.
     *
     * @var \Gaia\MVC\Models\User|null
     */
    private $agentUser;

    /**
     * The scope the token gets.
     *
     * @var string|null
     */
    private $grantedScope;

    /**
     * {@inheritdoc}
     */
    public function getQueryStringIdentifier()
    {
        return self::GRANT_TYPE;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Gaia\Exception\UnAuthorized When the runtime may not delegate or the agent may not be delegated
     */
    public function validateRequest(RequestInterface $request, ResponseInterface $response)
    {
        $this->agentUser = null;
        $this->grantedScope = null;

        if (!parent::validateRequest($request, $response)) {
            return false;
        }

        AgentDelegationGuard::assertRuntimeAllowed($this->getClientId());
        $delegable = AgentDelegationGuard::assertAgentDelegable((string) $request->request('agent_id'));

        $granted = AgentDelegationScopes::grant($delegable['scopes'], $request->request('scope'));
        if ($granted === null) {
            $response->setError(400, 'invalid_scope', 'The scope requested is not within the agent\'s delegation scopes');
            return false;
        }

        $this->agentUser = $delegable['user'];
        $this->grantedScope = $granted;

        return true;
    }

    /**
     * The agent user's email, as `RestController::setUser()` resolves users by it.
     *
     * @return string|null
     */
    public function getUserId()
    {
        return $this->agentUser ? $this->agentUser->email : null;
    }

    /**
     * The granted scope; bshaffer then checks the request's scope against it.
     *
     * @return string|null
     */
    public function getScope()
    {
        return $this->grantedScope;
    }

    /**
     * Issue the token without a refresh token and stamp the runtime as used.
     *
     * {@inheritdoc}
     */
    public function createAccessToken(AccessTokenInterface $accessToken, $client_id, $user_id, $scope)
    {
        $token = $accessToken->createAccessToken($client_id, $user_id, $scope, false);
        (new ServiceClients())->recordUse($client_id);

        return $token;
    }
}
