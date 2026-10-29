<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Agents;

use Gaia\Libraries\Tools\AgentToolGrants;
use Gaia\MVC\Models\Agent;

/**
 * Builds an agent's spec: the one document a runtime needs to run the agent.
 *
 * The document is engine-neutral and follows
 * `docs/schemas/agent-spec.schema.json` (version `SPEC_VERSION`): the agent,
 * its runtime config, its live tool grants (see `AgentToolGrants`, which never
 * returns a server's endpoint, command or spec) and its live skills with their
 * content (see `AgentSkillGrants`).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Library
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentSpec
{
    /**
     * Version of the spec document; raise it on a breaking change to the schema.
     */
    public const SPEC_VERSION = 1;

    /**
     * @var AgentToolGrants
     */
    private $toolGrants;

    /**
     * @var AgentSkillGrants
     */
    private $skillGrants;

    /**
     * @param AgentToolGrants|null  $toolGrants
     * @param AgentSkillGrants|null $skillGrants
     */
    public function __construct(AgentToolGrants $toolGrants = null, AgentSkillGrants $skillGrants = null)
    {
        $this->toolGrants = $toolGrants ?: new AgentToolGrants();
        $this->skillGrants = $skillGrants ?: new AgentSkillGrants();
    }

    /**
     * The spec of a live agent.
     *
     * @param  string $agentId
     * @return array|null Null when there is no live agent with this id
     */
    public function forAgent($agentId)
    {
        $agent = Agent::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $agentId],
        ]);
        if (!$agent) {
            return null;
        }

        return [
            'specVersion' => self::SPEC_VERSION,
            'agent' => [
                'id' => $agent->id,
                'userId' => $agent->userId,
                'name' => $agent->name,
                'purpose' => $agent->purpose,
                'sponsorId' => $this->nullIfBlank($agent->sponsorId),
            ],
            'config' => [
                'rolePrompt' => $agent->rolePrompt,
                'modelConfig' => $this->modelConfig($agent->modelConfig),
                'maxTurns' => $this->intOrNull($agent->maxTurns),
                'maxRunsPerIssue' => $this->intOrNull($agent->maxRunsPerIssue),
                'permissionMode' => $this->nullIfBlank($agent->permissionMode),
            ],
            'toolGrants' => $this->toolGrants->forAgent($agent->id),
            'skills' => $this->skillGrants->forAgent($agent->id),
        ];
    }

    /**
     * The stored model config as a JSON object (empty when unset).
     *
     * `agentConfigBehavior` only stores JSON objects, so anything else reads as empty.
     *
     * @param  string|null $stored
     * @return object
     */
    private function modelConfig($stored)
    {
        $decoded = ($stored === null || $stored === '') ? null : json_decode((string) $stored);

        return is_object($decoded) ? $decoded : new \stdClass();
    }

    /**
     * @param  mixed $value
     * @return int|null
     */
    private function intOrNull($value)
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    /**
     * @param  mixed $value
     * @return string|null
     */
    private function nullIfBlank($value)
    {
        return ($value === null || (string) $value === '') ? null : (string) $value;
    }
}
