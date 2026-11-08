<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

use Gaia\Libraries\Agents\AgentCredentials;
use Gaia\Libraries\Security\AgentLoginGuard;
use Gaia\Libraries\Security\ServiceClientGuard;
use OAuth2\GrantType\ClientCredentials;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;
use OAuth2\ResponseType\AccessTokenInterface;

/**
 * bshaffer's client_credentials grant, branching on the client's kind
 * (`ClientKind`) once the secret checks out:
 *
 * - a `service` client must be an active `Serviceclient`; its token has no
 *   user_id and carries the client's scopes;
 * - any other client must be bound to an active agent user, and the token is
 *   issued to that user. Tokens carry the user's email as user_id, as
 *   password-grant tokens do, because `RestController::setUser()` resolves the
 *   current user by it. An `app` client has no agent user, so it gets nothing.
 *
 * So a service client never gets a user's token and an agent client never a
 * service token.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ClientCredentialsGrant extends ClientCredentials
{
    /**
     * The agent user an agent client signs in as.
     *
     * @var \Gaia\MVC\Models\User|null
     */
    private $agentUser;

    /**
     * The service client a service client signs in as.
     *
     * @var \Gaia\MVC\Models\Serviceclient|null
     */
    private $serviceClient;

    /**
     * {@inheritdoc}
     *
     * @throws \Gaia\Exception\UnAuthorized When the client may not get a token
     */
    public function validateRequest(RequestInterface $request, ResponseInterface $response)
    {
        $this->agentUser = null;
        $this->serviceClient = null;

        if (!parent::validateRequest($request, $response)) {
            return false;
        }

        $clientId = $this->getClientId();
        if (ClientKind::of($clientId) === ClientKind::SERVICE) {
            $this->serviceClient = ServiceClientGuard::assertClientCredentialsAllowed($clientId);
        } else {
            $this->agentUser = AgentLoginGuard::assertClientCredentialsAllowed($clientId);
        }

        return true;
    }

    /**
     * The agent user's email; null for a service client.
     *
     * @return string|null
     */
    public function getUserId()
    {
        return $this->agentUser ? $this->agentUser->email : null;
    }

    /**
     * A service client's own scopes; an agent client's are the client row's.
     *
     * @return string|null
     */
    public function getScope()
    {
        if ($this->serviceClient) {
            return ServiceClientScopes::tokenScope($this->serviceClient);
        }

        return parent::getScope();
    }

    /**
     * Issue the token and stamp the client as used.
     *
     * {@inheritdoc}
     */
    public function createAccessToken(AccessTokenInterface $accessToken, $client_id, $user_id, $scope)
    {
        $token = parent::createAccessToken($accessToken, $client_id, $user_id, $scope);

        if ($this->serviceClient) {
            (new ServiceClients())->recordUse($client_id);
        } else {
            (new AgentCredentials())->recordUse($client_id);
        }

        return $token;
    }
}
