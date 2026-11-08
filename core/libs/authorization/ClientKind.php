<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

use Gaia\MVC\Models\Oauthclient;

/**
 * The kind of an OAuth client (`oauth_clients.kind`), which decides who a
 * client_credentials token is for:
 *
 * - `app`: an application people sign in through (password grant). It gets
 *   no client_credentials token.
 * - `agent`: an agent's client; the token is the agent user's.
 * - `service`: a service client (`Serviceclient`); the token has no user.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ClientKind
{
    /**
     * An application people sign in through.
     */
    public const APP = 'app';

    /**
     * An agent's client.
     */
    public const AGENT = 'agent';

    /**
     * A service client.
     */
    public const SERVICE = 'service';

    /**
     * The kind of the client, or null when there is no such client.
     *
     * @param  string $clientId
     * @return string|null
     */
    public static function of($clientId)
    {
        $client = Oauthclient::findFirst([
            'conditions' => 'client_id = :clientId:',
            'bind' => ['clientId' => (string) $clientId],
        ]);
        if (!$client) {
            return null;
        }

        $kind = (string) $client->kind;
        return $kind === '' ? self::APP : $kind;
    }
}
