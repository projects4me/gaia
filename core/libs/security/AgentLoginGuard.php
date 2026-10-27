<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\Exception\UnAuthorized;
use Gaia\MVC\Models\Oauthclient;
use Gaia\MVC\Models\User;

/**
 * Which OAuth grants a user may sign in with: agents use client_credentials
 * only, people use password (and refresh_token) only.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Security
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentLoginGuard
{
    /**
     * Error for a password or refresh_token grant by an agent.
     */
    public const AGENT_PASSWORD_ERROR = 'Agents sign in with their client credentials.';

    /**
     * Error for a client_credentials grant by a client that is not an active agent.
     */
    public const CLIENT_NOT_AGENT_ERROR = 'Client credentials are only for active agents.';

    /**
     * Reject a password or refresh_token grant for an agent user.
     *
     * @param  User $user
     * @return void
     * @throws UnAuthorized
     */
    public static function assertPasswordGrantAllowed(User $user)
    {
        if ($user->isAgent()) {
            throw new UnAuthorized(self::AGENT_PASSWORD_ERROR);
        }
    }

    /**
     * The agent user a client signs in as, or a rejection when the client is
     * not bound to a live, active agent user.
     *
     * @param  string $clientId
     * @return User
     * @throws UnAuthorized
     */
    public static function assertClientCredentialsAllowed($clientId)
    {
        $client = Oauthclient::findFirst([
            'conditions' => 'client_id = :clientId:',
            'bind' => ['clientId' => (string) $clientId],
        ]);
        $userId = $client ? (string) $client->user_id : '';

        $user = $userId === '' ? false : User::findFirst([
            'conditions' => 'id = :id: AND deleted = 0',
            'bind' => ['id' => $userId],
        ]);

        if (!$user
            || !$user->isAgent()
            || !AclLockoutGuard::isAuthenticatableAccountStatus($user->accountStatus)
        ) {
            throw new UnAuthorized(self::CLIENT_NOT_AGENT_ERROR);
        }

        return $user;
    }
}
