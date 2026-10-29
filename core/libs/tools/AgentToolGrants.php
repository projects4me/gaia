<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Tools;

use Gaia\MVC\Models\Agenttool;
use Gaia\MVC\Models\Tool;
use Gaia\MVC\Models\Toolserver;

/**
 * An agent's live tool grants, shaped for the services that enforce them.
 *
 * A grant names either a whole tool server or one tool. Each grant comes back
 * with its permission rule, its server and, for a tool grant, the tool. A
 * grant whose tool or server has been deleted is left out. Only identifying
 * fields are returned: never a server's endpoint, command or spec, which may
 * hold credentials.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Library
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentToolGrants
{
    /**
     * @var array Servers read so far, by id (false when not live)
     */
    private $servers = [];

    /**
     * The agent's live grants.
     *
     * @param  string $agentId
     * @return array[] Each ['id', 'permissionRule', 'server' => array|null, 'tool' => array|null]
     */
    public function forAgent($agentId)
    {
        $grants = Agenttool::find([
            'conditions' => 'agentId = :agentId: AND deleted = false',
            'bind' => ['agentId' => (string) $agentId],
            'order' => 'dateCreated, id',
        ]);

        $shaped = [];
        foreach ($grants as $grant) {
            $entry = $this->shape($grant);
            if ($entry !== null) {
                $shaped[] = $entry;
            }
        }

        return $shaped;
    }

    /**
     * @param  Agenttool $grant
     * @return array|null Null when the granted tool or server is gone, or the grant names neither
     */
    private function shape(Agenttool $grant)
    {
        $tool = null;
        $serverId = $grant->toolServerId;

        if (!$this->isBlank($grant->toolId)) {
            $tool = $this->liveTool($grant->toolId);
            if ($tool === null) {
                return null;
            }
            $serverId = $tool->toolServerId;
        }

        $server = $this->isBlank($serverId) ? null : $this->liveServer($serverId);
        if (!$this->isBlank($serverId) && $server === null) {
            return null;
        }
        if ($tool === null && $server === null) {
            return null;
        }

        return [
            'id' => $grant->id,
            'permissionRule' => $grant->permissionRule,
            'server' => $server === null ? null : [
                'id' => $server->id,
                'name' => $server->name,
                'kind' => $server->kind,
                'location' => $server->location,
                'transport' => $server->transport,
            ],
            'tool' => $tool === null ? null : [
                'id' => $tool->id,
                'name' => $tool->name,
            ],
        ];
    }

    /**
     * @param  string $toolId
     * @return Tool|null
     */
    private function liveTool($toolId)
    {
        $tool = Tool::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $toolId],
        ]);

        return $tool ?: null;
    }

    /**
     * @param  string $serverId
     * @return Toolserver|null
     */
    private function liveServer($serverId)
    {
        $serverId = (string) $serverId;
        if (!array_key_exists($serverId, $this->servers)) {
            $this->servers[$serverId] = Toolserver::findFirst([
                'conditions' => 'id = :id: AND deleted = false',
                'bind' => ['id' => $serverId],
            ]);
        }

        return $this->servers[$serverId] ?: null;
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    private function isBlank($value)
    {
        return $value === null || (string) $value === '';
    }
}
