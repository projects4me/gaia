<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

use Gaia\Libraries\Agents\AgentCredentials;
use Gaia\MVC\Models\Oauthclient;
use Gaia\MVC\Models\Serviceclient;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Issue a new service client: its `oauth_clients` row (kind `service`, no
 * user, grant type client_credentials) holding the hash of a new secret, and
 * the `Serviceclient` that names it. The client gets its kind's default
 * scopes (`serviceClientBehavior`). The secret is returned once and only its
 * hash is stored.
 *
 * Runs inside the caller's transaction and saves as the caller's current user.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceClientCredentials
{
    /**
     * Service client ids are `service-<random hex>`.
     */
    public const CLIENT_ID_PREFIX = 'service-';

    /**
     * The only grant a service client may use.
     */
    public const GRANT_TYPES = 'client_credentials';

    /**
     * Random bytes in a client id (hex encoded after the prefix, 36 characters in all).
     */
    private const CLIENT_ID_BYTES = 14;

    /**
     * Random bytes in a secret (hex encoded after the prefix).
     */
    private const SECRET_BYTES = 24;

    /**
     * Create the service client and its secret.
     *
     * @param  string $name
     * @param  string $kind A configured kind of service
     * @return array ['serviceclient' => Serviceclient, 'clientId' => string, 'secret' => string]
     * @throws \Gaia\Exception\BadRequest When a row cannot be saved
     */
    public function issue($name, $kind)
    {
        $clientId = self::CLIENT_ID_PREFIX . bin2hex(random_bytes(self::CLIENT_ID_BYTES));
        $secret = AgentCredentials::SECRET_PREFIX . bin2hex(random_bytes(self::SECRET_BYTES));

        $client = new Oauthclient();
        $client->id = create_guid();
        $client->client_id = $clientId;
        $client->client_secret = password_hash($secret, PASSWORD_DEFAULT);
        $client->redirect_uri = '';
        $client->grant_types = self::GRANT_TYPES;
        $client->scope = null;
        $client->user_id = null;
        $client->kind = ClientKind::SERVICE;
        $this->save($client);

        $serviceClient = new Serviceclient();
        $serviceClient->id = create_guid();
        $serviceClient->name = (string) $name;
        $serviceClient->kind = (string) $kind;
        $serviceClient->status = ServiceClients::STATUS_ACTIVE;
        $serviceClient->clientId = $clientId;
        $this->save($serviceClient);

        return ['serviceclient' => $serviceClient, 'clientId' => $clientId, 'secret' => $secret];
    }

    /**
     * Save as the current user.
     *
     * @param  \Phalcon\Mvc\ModelInterface $model
     * @return void
     * @throws \Gaia\Exception\BadRequest
     */
    protected function save($model)
    {
        (new ActingUser())->save($model, null, 'The service client could not be saved.');
    }
}
