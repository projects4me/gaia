<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Agents;

use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Agentcredential;
use Gaia\MVC\Models\Oauthclient;

use function Gaia\Libraries\Utils\create_guid;

/**
 * The lifecycle of an agent's login credential: issue (and so rotate),
 * revoke, and the last-used stamp.
 *
 * Each agent has one `oauth_clients` row bound to its user (client id
 * `agent-<handle>`, grant type client_credentials) that holds the hash of the
 * current secret. An `agentcredentials` row records each secret issued: who
 * created it, when, and when it was last used. Issuing a new secret replaces
 * the hash, soft-deletes the previous record and deletes the client's access
 * tokens, so the old secret and its tokens stop working at once.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Library
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentCredentials
{
    /**
     * Every secret starts with this, so secret scanners can find leaked ones.
     */
    public const SECRET_PREFIX = 'p4m_cs_';

    /**
     * Agent client ids are `agent-<handle>`, the handle made from the agent's name.
     */
    public const CLIENT_ID_PREFIX = 'agent-';

    /**
     * The only grant an agent client may use.
     */
    public const GRANT_TYPES = 'client_credentials';

    /**
     * Length of the client_id columns of the oauth tables.
     */
    private const CLIENT_ID_LENGTH = 36;

    /**
     * Random bytes in a secret (hex encoded after the prefix).
     */
    private const SECRET_BYTES = 24;

    /**
     * Issue a new secret for the agent, revoking the one it replaces.
     *
     * The secret is returned once and only its hash is stored.
     *
     * @param  string $agentId
     * @return array ['credential' => Agentcredential, 'secret' => string]
     * @throws \Gaia\Exception\Permission When the agent does not exist
     */
    public function issue($agentId)
    {
        return (new AgentLifecycle())->transactional(function () use ($agentId) {
            // Locks the agent row, so two issues for one agent run one after the other.
            $agent = $this->findLiveAgent($agentId, true);
            if (!$agent) {
                $this->reject('Credentials can only be issued for an existing agent.');
            }

            $this->deleteCredentialRecords($agent->id);

            $client = $this->findClientOfUser($agent->userId);
            if (!$client) {
                $client = new Oauthclient();
                $client->id = create_guid();
                $client->client_id = $this->newClientId($agent);
            }

            $secret = self::SECRET_PREFIX . bin2hex(random_bytes(self::SECRET_BYTES));
            $client->client_secret = password_hash($secret, PASSWORD_DEFAULT);
            $client->redirect_uri = '';
            $client->grant_types = self::GRANT_TYPES;
            $client->scope = null;
            $client->user_id = $agent->userId;
            $this->save($client);
            $this->deleteAccessTokens($client->client_id);

            $credential = new Agentcredential();
            $credential->id = create_guid();
            $credential->agentId = $agent->id;
            $credential->clientId = $client->client_id;
            $this->save($credential);

            return ['credential' => $credential, 'secret' => $secret];
        });
    }

    /**
     * Cut a client off: delete its oauth_clients row and its access tokens.
     *
     * @param  string $clientId
     * @return void
     * @throws \Gaia\Exception\Permission When a row cannot be deleted
     */
    public function revokeClient($clientId)
    {
        $client = Oauthclient::findFirst([
            'conditions' => 'client_id = :clientId:',
            'bind' => ['clientId' => (string) $clientId],
        ]);
        if ($client && !$client->delete()) {
            $this->reject('The agent credential could not be revoked.');
        }

        $this->deleteAccessTokens($clientId);
    }

    /**
     * Revoke everything an agent signs in with (used when the agent is deleted).
     *
     * @param  Agent $agent
     * @return void
     */
    public function revokeAgent(Agent $agent)
    {
        $this->deleteCredentialRecords($agent->id);

        $client = $this->findClientOfUser($agent->userId);
        if ($client) {
            $this->revokeClient($client->client_id);
        }
    }

    /**
     * Stamp the client's live credential as used now.
     *
     * A bound UPDATE, not a model save: a usage stamp is not an edit, so it
     * leaves the audit trail and modifiedUser alone (there is no current user
     * while a token is issued).
     *
     * A failed stamp does not stop the sign-in: the caller issues the token
     * either way.
     *
     * @param  string $clientId
     * @return bool Whether the stamp was written
     */
    public function recordUse($clientId)
    {
        try {
            return (bool) $this->db()->execute(
                'UPDATE agentcredentials SET "lastUsedAt" = ? WHERE "clientId" = ? AND deleted = false',
                [gmdate('Y-m-d H:i:s'), (string) $clientId]
            );
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The live (not revoked) credential with this id.
     *
     * @param  string $credentialId
     * @return Agentcredential|null
     */
    public function findLive($credentialId)
    {
        $credential = Agentcredential::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $credentialId],
        ]);

        return $credential ?: null;
    }

    /**
     * The database connection the usage stamp writes through.
     *
     * @return \Phalcon\Db\Adapter\AdapterInterface
     */
    protected function db()
    {
        return \Phalcon\Di::getDefault()->getShared('db');
    }

    /**
     * The client id for an agent's first credential: `agent-<handle>`, with a
     * random suffix when another client already holds it.
     *
     * @param  Agent $agent
     * @return string
     */
    private function newClientId(Agent $agent)
    {
        $suffixLength = 7;
        $handleLength = self::CLIENT_ID_LENGTH - strlen(self::CLIENT_ID_PREFIX) - $suffixLength;
        $handle = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $agent->name)), '-');
        $handle = rtrim(substr($handle, 0, $handleLength), '-');
        if ($handle === '') {
            $handle = 'agent';
        }

        $clientId = self::CLIENT_ID_PREFIX . $handle;
        while ($this->clientIdTaken($clientId)) {
            $clientId = self::CLIENT_ID_PREFIX . $handle . '-' . bin2hex(random_bytes(3));
        }

        return $clientId;
    }

    /**
     * @param  string $clientId
     * @return bool
     */
    private function clientIdTaken($clientId)
    {
        return (bool) Oauthclient::findFirst([
            'conditions' => 'client_id = :clientId:',
            'bind' => ['clientId' => $clientId],
        ]);
    }

    /**
     * Soft-delete the agent's live credential records.
     *
     * @param  string $agentId
     * @return void
     */
    private function deleteCredentialRecords($agentId)
    {
        $credentials = Agentcredential::find([
            'conditions' => 'agentId = :agentId: AND deleted = false',
            'bind' => ['agentId' => (string) $agentId],
        ]);
        foreach ($credentials as $credential) {
            if (!$credential->delete()) {
                $this->reject('The previous agent credential could not be revoked.');
            }
        }
    }

    /**
     * Delete every access token issued to the client.
     *
     * @param  string $clientId
     * @return void
     */
    private function deleteAccessTokens($clientId)
    {
        \Phalcon\Di::getDefault()->get('modelsManager')->executeQuery(
            'DELETE FROM [Gaia\MVC\Models\Oauthaccesstoken] WHERE client_id = :clientId:',
            ['clientId' => (string) $clientId]
        );
    }

    /**
     * The user's agent client (a client_credentials client bound to it).
     *
     * @param  string $userId
     * @return Oauthclient|null
     */
    private function findClientOfUser($userId)
    {
        $client = Oauthclient::findFirst([
            'conditions' => 'user_id = :userId: AND grant_types = :grantTypes:',
            'bind' => ['userId' => (string) $userId, 'grantTypes' => self::GRANT_TYPES],
        ]);

        return $client ?: null;
    }

    /**
     * @param  mixed $agentId
     * @param  bool  $lock Lock the row until the transaction ends
     * @return Agent|null
     */
    private function findLiveAgent($agentId, $lock = false)
    {
        if ($agentId === null || (string) $agentId === '') {
            return null;
        }

        $agent = Agent::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $agentId],
            'for_update' => $lock,
        ]);

        return $agent ?: null;
    }

    /**
     * Save a model or reject with its validation messages.
     *
     * @param  \Phalcon\Mvc\Model $model
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    private function save($model)
    {
        if ($model->save()) {
            return;
        }

        $messages = [];
        foreach ($model->getMessages() as $message) {
            $messages[] = $message->getMessage();
        }
        $this->reject(implode(' ', $messages) ?: 'The agent credential could not be saved.');
    }

    /**
     * @param  string $message
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    private function reject($message)
    {
        throw new \Gaia\Exception\Permission($message);
    }
}
