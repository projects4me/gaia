<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

use Gaia\Libraries\Agents\AgentCredentials;
use Gaia\MVC\Models\Oauthclient;
use Gaia\MVC\Models\Serviceclient;

/**
 * Rotating a service client's secret (D-067): a new secret replaces the
 * current one, and the current one stays valid for a grace period so a service
 * that crashes before it stores the new secret can still sign in and rotate
 * again. Only hashes are stored; the new secret is returned once.
 *
 * The client's access tokens are kept: they expire on their own, and the
 * service rotates with one of them.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceClientSecrets
{
    /**
     * How long the previous secret stays valid after a rotation.
     */
    public const GRACE_SECONDS = 600;

    /**
     * Error when the client is not a service client.
     */
    public const NOT_SERVICE_ERROR = 'Only a service client secret can be rotated here.';

    /**
     * Error when the new secret cannot be stored.
     */
    public const SAVE_ERROR = 'The service client secret could not be rotated.';

    /**
     * Random bytes in a secret (hex encoded after the prefix).
     */
    private const SECRET_BYTES = 24;

    /**
     * Rotate the secret of the service client record `$serviceClientId`
     * (the serviceclient id, not the oauth client_id).
     *
     * @param  string $serviceClientId
     * @return array ['clientId' => string, 'secret' => string, 'previousSecretExpiresAt' => string]
     * @throws \Gaia\Exception\Permission When there is no such live service client
     */
    public function rotateServiceClient($serviceClientId)
    {
        $serviceClient = Serviceclient::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $serviceClientId],
        ]);
        if (!$serviceClient) {
            throw new \Gaia\Exception\Permission(self::NOT_SERVICE_ERROR);
        }

        $clientId = (string) $serviceClient->clientId;
        return ['clientId' => $clientId] + $this->rotate($clientId);
    }

    /**
     * Replace the client's secret, keeping the current one valid for
     * `GRACE_SECONDS`.
     *
     * @param  string $clientId The oauth client_id of a service client.
     * @return array ['secret' => string, 'previousSecretExpiresAt' => string (UTC, Y-m-d H:i:s)]
     * @throws \Gaia\Exception\Permission When the client is not a service client or cannot be saved
     */
    public function rotate($clientId)
    {
        $db = $this->db();
        $db->begin();

        try {
            $result = $this->replaceSecret((string) $clientId);
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
     * @param  string $clientId
     * @return array
     * @throws \Gaia\Exception\Permission
     */
    protected function replaceSecret($clientId)
    {
        // Locks the row, so two rotations of one client run one after the other.
        $client = Oauthclient::findFirst([
            'conditions' => 'client_id = :clientId:',
            'bind' => ['clientId' => $clientId],
            'for_update' => true,
        ]);
        if (!$client || (string) $client->kind !== ClientKind::SERVICE || (string) $client->user_id !== '') {
            throw new \Gaia\Exception\Permission(self::NOT_SERVICE_ERROR);
        }

        $secret = AgentCredentials::SECRET_PREFIX . bin2hex(random_bytes(self::SECRET_BYTES));
        $expiresAt = gmdate('Y-m-d H:i:s', $this->now() + self::GRACE_SECONDS);

        $client->previous_client_secret = $client->client_secret;
        $client->previous_secret_expires = $expiresAt;
        $client->client_secret = password_hash($secret, PASSWORD_DEFAULT);
        if (!$client->save()) {
            throw new \Gaia\Exception\Permission(self::SAVE_ERROR);
        }

        return ['secret' => $secret, 'previousSecretExpiresAt' => $expiresAt];
    }

    /**
     * The current time, as a Unix timestamp.
     *
     * @return int
     */
    protected function now()
    {
        return time();
    }

    /**
     * The database connection the rotation runs its transaction on.
     *
     * @return \Phalcon\Db\Adapter\AdapterInterface
     */
    protected function db()
    {
        return \Phalcon\Di::getDefault()->getShared('db');
    }
}
