<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Agents\AgentCredentials;
use Gaia\Libraries\Agents\AgentLifecycle;
use Gaia\Libraries\Security\AgentEscalationGuard;
use Gaia\MVC\Models\Agentcredential;

/**
 * Agentcredential Controller
 *
 * An agent's login credential (see `AgentCredentials`). POST `{agentId}`
 * issues a secret, revoking the agent's previous one; the response carries
 * the secret as `clientSecret`, the only time it is shown. DELETE revokes it.
 * A credential cannot be changed, and an agent cannot manage its own
 * (see `AgentEscalationGuard`).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentcredentialController extends RestController
{
    /**
     * Error for an update of a credential.
     */
    public const UNCHANGEABLE_ERROR = 'An agent credential cannot be changed. Issue a new one to rotate it.';

    /**
     * Issue a new secret for `agentId`.
     *
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\Permission
     */
    public function postAction()
    {
        global $currentUser;

        if (isset($this->id)) {
            return $this->patchAction();
        }

        $attributes = $this->extractRequestAttributes();
        $agentId = isset($attributes['agentId']) ? $attributes['agentId'] : null;
        AgentEscalationGuard::assertNotOwnAgent($currentUser, $agentId);

        $issued = (new AgentCredentials())->issue($agentId);
        $credential = $issued['credential'];

        $this->response->setStatusCode(201, "Created");
        $data = $credential->read(['id' => $credential->id]);
        $dataArray = $this->extractData($data, [], false, 'one');
        $document = $this->buildHAL($dataArray);
        $document['data']['attributes']['clientSecret'] = $issued['secret'];
        return $this->returnResponse($document);
    }

    /**
     * Credentials are replaced, never changed.
     *
     * @throws \Gaia\Exception\Permission
     */
    public function patchAction()
    {
        throw new \Gaia\Exception\Permission(self::UNCHANGEABLE_ERROR);
    }

    /**
     * Credentials are replaced, never changed.
     *
     * @throws \Gaia\Exception\Permission
     */
    public function patchCollectionAction()
    {
        throw new \Gaia\Exception\Permission(self::UNCHANGEABLE_ERROR);
    }

    /**
     * Revoke the credential: soft-delete it and cut its client off.
     *
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\Permission
     */
    public function deleteAction()
    {
        global $currentUser;

        $credential = Agentcredential::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $this->id],
        ]);
        if (!$credential) {
            return parent::deleteAction();
        }

        AgentEscalationGuard::assertNotOwnAgent($currentUser, $credential->agentId);
        $clientId = (string) $credential->clientId;

        return (new AgentLifecycle())->transactional(function () use ($clientId) {
            $response = parent::deleteAction();
            if ((int) $response->getStatusCode() === 200) {
                (new AgentCredentials())->revokeClient($clientId);
            }
            return $response;
        });
    }
}
