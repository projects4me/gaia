<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

use Gaia\Libraries\Agents\AgentCredentials;
use Gaia\Libraries\Security\AgentLoginGuard;
use OAuth2\GrantType\ClientCredentials;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use OAuth2\ResponseType\AccessTokenInterface;

/**
 * bshaffer's client_credentials grant for agents: after the secret checks
 * out, the client must be bound to an active agent user, and the token is
 * issued to that user.
 *
 * Tokens carry the user's email as user_id, as password-grant tokens do,
 * because `RestController::setUser()` resolves the current user by it.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentClientCredentials extends ClientCredentials
{
    /**
     * The agent user the client signs in as.
     *
     * @var \Gaia\MVC\Models\User|null
     */
    private $agentUser;

    /**
     * {@inheritdoc}
     *
     * @throws \Gaia\Exception\UnAuthorized When the client is not an active agent
     */
    public function validateRequest(RequestInterface $request, ResponseInterface $response)
    {
        if (!parent::validateRequest($request, $response)) {
            return false;
        }

        $this->agentUser = AgentLoginGuard::assertClientCredentialsAllowed($this->getClientId());
        return true;
    }

    /**
     * The agent user's email.
     *
     * @return string|null
     */
    public function getUserId()
    {
        return $this->agentUser ? $this->agentUser->email : null;
    }

    /**
     * Issue the token and stamp the credential as used.
     *
     * {@inheritdoc}
     */
    public function createAccessToken(AccessTokenInterface $accessToken, $client_id, $user_id, $scope)
    {
        $token = parent::createAccessToken($accessToken, $client_id, $user_id, $scope);
        (new AgentCredentials())->recordUse($client_id);

        return $token;
    }
}
