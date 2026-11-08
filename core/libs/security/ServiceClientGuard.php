<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\Exception\UnAuthorized;
use Gaia\Libraries\Authorization\ClientKind;
use Gaia\Libraries\Authorization\ServiceClients;
use Gaia\MVC\Models\Oauthclient;
use Gaia\MVC\Models\Serviceclient;

/**
 * Whether a client may get a service token: it must be a `service` client
 * bound to no user, with a live, active `Serviceclient`.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Security
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceClientGuard
{
    /**
     * Error for a client_credentials grant by a client that is not an active service client.
     */
    public const CLIENT_NOT_SERVICE_ERROR = 'Client credentials are only for active service clients.';

    /**
     * The service client a client signs in as, or a rejection.
     *
     * @param  string $clientId
     * @return Serviceclient
     * @throws UnAuthorized
     */
    public static function assertClientCredentialsAllowed($clientId)
    {
        $client = Oauthclient::findFirst([
            'conditions' => 'client_id = :clientId:',
            'bind' => ['clientId' => (string) $clientId],
        ]);
        if (!$client
            || (string) $client->kind !== ClientKind::SERVICE
            || (string) $client->user_id !== ''
        ) {
            throw new UnAuthorized(self::CLIENT_NOT_SERVICE_ERROR);
        }

        $serviceClient = Serviceclient::findFirst([
            'conditions' => 'clientId = :clientId: AND deleted = false',
            'bind' => ['clientId' => (string) $clientId],
        ]);
        if (!$serviceClient || (string) $serviceClient->status !== ServiceClients::STATUS_ACTIVE) {
            throw new UnAuthorized(self::CLIENT_NOT_SERVICE_ERROR);
        }

        return $serviceClient;
    }

    /**
     * The service client a user-less access token was issued to, or a
     * rejection: its client must still be an active service client, so a
     * disabled or deleted client's tokens stop working (D-066).
     *
     * @param  \Gaia\MVC\Models\Oauthaccesstoken $accessToken
     * @return Serviceclient
     * @throws UnAuthorized
     */
    public static function assertServiceTokenAllowed($accessToken)
    {
        $clientId = isset($accessToken->client_id) ? (string) $accessToken->client_id : '';
        if ($clientId === '') {
            throw new UnAuthorized(self::CLIENT_NOT_SERVICE_ERROR);
        }

        return self::assertClientCredentialsAllowed($clientId);
    }
}
