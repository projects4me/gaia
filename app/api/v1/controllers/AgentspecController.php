<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Agents\AgentSpec;

/**
 * Agent Spec Controller
 *
 * Read-only: `GET /api/v1/agentspec/{agentId}` returns the agent's spec (see
 * `AgentSpec`), the document a runtime uses to run or cache the agent. Reading
 * it needs `agentspec.get`, granted apart from `agent.*`.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentspecController extends RestController
{
    private const BY_ID_ONLY = 'Agent specs are read one at a time, by agent id.';

    /**
     * Only get is exposed. The list route is checked against agentspec.get too,
     * so a caller with that permission gets the 404 from listAction.
     *
     * @var array $aclMap
     */
    protected $aclMap = array(
        'get' => array(
            'action' => 'get',
            'controllerAction' => 'getAction',
        ),
        'list' => array(
            'action' => 'get',
            'controllerAction' => 'listAction',
        ),
    );

    /**
     * The spec of the agent named by the id.
     *
     * @throws \Gaia\Exception\ResourceNotFound When there is no live agent with this id
     * @return \Phalcon\Http\Response
     */
    public function getAction()
    {
        if (!(isset($this->id) && $this->id !== '')) {
            throw new \Gaia\Exception\ResourceNotFound('Agent not found');
        }

        if ($this->getDI()->has('acl')) {
            $this->getDI()->get('acl')->assertRecordIdInScope('Agent', $this->id);
        }

        $spec = (new AgentSpec())->forAgent($this->id);
        if ($spec === null) {
            throw new \Gaia\Exception\ResourceNotFound('Agent not found');
        }

        return $this->returnResponse($spec);
    }

    /**
     * There is no list of specs: the GET route also maps `/agentspec`, so refuse it.
     *
     * @throws \Gaia\Exception\ResourceNotFound
     * @return void
     */
    public function listAction()
    {
        throw new \Gaia\Exception\ResourceNotFound(self::BY_ID_ONLY);
    }
}
