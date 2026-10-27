<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Agents\AgentLifecycle;
use Gaia\Libraries\Security\AgentEscalationGuard;
use Gaia\MVC\Models\Agent;

/**
 * Agent Controller
 *
 * Creating an agent creates its users row too, and deleting it deletes that
 * user (see `AgentLifecycle`). An agent cannot change or delete its own agent
 * (see `AgentEscalationGuard`).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentController extends RestController
{
    /**
     * Create the agent and its users row in one transaction.
     *
     * The payload holds the agent fields plus the user's `email` and an
     * optional `accountStatus` (default Active).
     *
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        if (isset($this->id)) {
            return $this->patchAction();
        }

        $attributes = $this->extractRequestAttributes();
        $userFields = array_flip(AgentLifecycle::USER_FIELDS);
        $userAttributes = array_intersect_key($attributes, $userFields);
        $agentAttributes = array_diff_key($attributes, $userFields);

        if ($this->getDI()->has('acl')) {
            $acl = $this->getDI()->get('acl');
            $agentAttributes = $acl->authorizeWritableFields('Agent', $agentAttributes, 'create');
            $userAttributes = $acl->authorizeWritableFields('User', $userAttributes, 'create');
        }

        $agent = (new AgentLifecycle())->create($agentAttributes + $userAttributes);

        $this->response->setStatusCode(201, "Created");
        $data = $agent->read(['id' => $agent->id]);
        $dataArray = $this->extractData($data, [], false, 'one');
        return $this->returnResponse($this->buildHAL($dataArray));
    }

    /**
     * Update the agent; a new name is copied to its users row.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        $attributes = $this->extractRequestAttributes();
        $agentId = isset($attributes['id']) && $attributes['id'] !== '' ? $attributes['id'] : $this->id;
        $this->assertAgentWritable($agentId, $attributes);

        $lifecycle = new AgentLifecycle();
        return $lifecycle->transactional(function () use ($lifecycle, $agentId, $attributes) {
            $response = parent::patchAction();
            if ((int) $response->getStatusCode() === 200 && array_key_exists('name', $attributes)) {
                $agent = Agent::findFirst([
                    'conditions' => 'id = :id:',
                    'bind' => ['id' => (string) $agentId],
                ]);
                if ($agent) {
                    $lifecycle->syncUserName($agent);
                }
            }
            return $response;
        });
    }

    /**
     * Apply the agent write checks to every record of a collection patch.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchCollectionAction()
    {
        foreach ($this->extractCollectionAttributes() as $attributes) {
            $this->assertAgentWritable(isset($attributes['id']) ? $attributes['id'] : null, $attributes);
        }

        return parent::patchCollectionAction();
    }

    /**
     * Delete the agent and its users row in one transaction.
     *
     * @throws \Gaia\Exception\Access
     * @throws \Gaia\Exception\Permission
     * @return \Phalcon\Http\Response
     */
    public function deleteAction()
    {
        global $currentUser;

        AgentEscalationGuard::assertNotOwnAgent($currentUser, $this->id);

        $agent = Agent::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $this->id],
        ]);
        if (!$agent) {
            return parent::deleteAction();
        }

        $lifecycle = new AgentLifecycle();
        $lifecycle->assertDeletable($agent);
        $userId = (string) $agent->userId;

        return $lifecycle->transactional(function () use ($lifecycle, $userId) {
            $response = parent::deleteAction();
            if ((int) $response->getStatusCode() === 200) {
                $lifecycle->deleteUser($userId);
            }
            return $response;
        });
    }

    /**
     * The checks every agent update passes: an agent cannot change its own
     * agent, and a sponsor must be an existing user.
     *
     * @param  string|null $agentId
     * @param  array       $attributes
     * @throws \Gaia\Exception\Access
     * @throws \Gaia\Exception\Permission
     * @return void
     */
    private function assertAgentWritable($agentId, array $attributes)
    {
        global $currentUser;

        AgentEscalationGuard::assertNotOwnAgent($currentUser, $agentId);

        if (array_key_exists('sponsorId', $attributes)) {
            (new AgentLifecycle())->assertSponsorExists($attributes['sponsorId']);
        }
    }
}
