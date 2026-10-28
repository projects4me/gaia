<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Security\AgentEscalationGuard;
use Gaia\Libraries\Tools\ToolCatalogRules;

/**
 * Agenttool Controller
 *
 * A tool grant names an agent and either a tool server or one tool (see
 * `ToolCatalogRules`). An agent cannot write its own grants (see
 * `AgentEscalationGuard`).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgenttoolController extends RestController
{
    /**
     * Create a grant.
     *
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        if (isset($this->id)) {
            return $this->patchAction();
        }

        $this->assertGrantWritable(null, $this->extractRequestAttributes());
        return parent::postAction();
    }

    /**
     * Update a grant. The record updated is the one in the body
     * (RestController::patchAction), so that is the one checked.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        $attributes = $this->extractRequestAttributes();
        $grantId = isset($attributes['id']) && $attributes['id'] !== '' ? $attributes['id'] : $this->id;

        $this->assertGrantWritable($grantId, $attributes);
        return parent::patchAction();
    }

    /**
     * Update several grants, each checked like a single update.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchCollectionAction()
    {
        foreach ($this->extractCollectionAttributes() as $attributes) {
            $this->assertGrantWritable(isset($attributes['id']) ? $attributes['id'] : null, $attributes);
        }
        return parent::patchCollectionAction();
    }

    /**
     * Delete a grant.
     *
     * @return \Phalcon\Http\Response
     */
    public function deleteAction()
    {
        global $currentUser;

        AgentEscalationGuard::assertNotOwnToolGrant($currentUser, $this->id, null);
        return parent::deleteAction();
    }

    /**
     * The checks every grant write passes.
     *
     * @param  string|null $grantId
     * @param  array       $attributes
     * @return void
     * @throws \Gaia\Exception\Access
     * @throws \Gaia\Exception\Permission
     */
    private function assertGrantWritable($grantId, array $attributes)
    {
        global $currentUser;

        AgentEscalationGuard::assertNotOwnToolGrant(
            $currentUser,
            $grantId,
            isset($attributes['agentId']) ? $attributes['agentId'] : null
        );
        (new ToolCatalogRules())->assertGrant($attributes, $grantId);
    }
}
