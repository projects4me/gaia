<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\Exception\UnAuthorized;
use Gaia\Libraries\Agents\AgentDelegationScopes;
use Gaia\Libraries\Authorization\ServiceClientScopes;
use Gaia\Libraries\Workspaces\WorkspaceRuntime;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Serviceclient;
use Gaia\MVC\Models\User;

/**
 * Whether a runtime may get a token acting as an agent (D-067): the client
 * must be an active service client holding the `agent.delegate` scope, and
 * the agent must be live, its user an active agent, with delegation scopes,
 * and its workspace bound to the calling runtime (D-073).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Security
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentDelegationGuard
{
    /**
     * The service client scope that allows the delegation grant.
     */
    public const DELEGATE_SCOPE = 'agent.delegate';

    /**
     * Error for a client that may not delegate.
     */
    public const CLIENT_NOT_RUNTIME_ERROR = 'Agent delegation is only for active service clients with the agent.delegate scope.';

    /**
     * Error for an agent that may not be delegated.
     */
    public const AGENT_NOT_DELEGABLE_ERROR = 'The agent does not exist, is not active or cannot be delegated.';

    /**
     * The service client a runtime signs in as, or a rejection when it may not
     * delegate. `agent.delegate` must be listed by name: a pattern such as
     * `agent.*` does not allow it.
     *
     * @param  string $clientId
     * @return Serviceclient
     * @throws UnAuthorized
     */
    public static function assertRuntimeAllowed($clientId)
    {
        try {
            $client = ServiceClientGuard::assertClientCredentialsAllowed($clientId);
        } catch (UnAuthorized $e) {
            throw new UnAuthorized(self::CLIENT_NOT_RUNTIME_ERROR);
        }

        $scopes = ServiceClientScopes::decode($client->scopes);
        if (!$scopes || !in_array(self::DELEGATE_SCOPE, $scopes, true)) {
            throw new UnAuthorized(self::CLIENT_NOT_RUNTIME_ERROR);
        }

        return $client;
    }

    /**
     * The agent and its user, or a rejection when the agent may not be
     * delegated by the runtime: missing or deleted, its user missing, not an
     * agent or not active, no delegation scopes, or its workspace missing or
     * not bound to the runtime (D-073).
     *
     * @param  string        $agentId
     * @param  Serviceclient $runtime The client {@see assertRuntimeAllowed()} returned
     * @return array ['agent' => Agent, 'user' => User, 'scopes' => string[]]
     * @throws UnAuthorized
     */
    public static function assertAgentDelegable($agentId, Serviceclient $runtime)
    {
        $agent = (string) $agentId === '' ? false : Agent::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $agentId],
        ]);
        $user = !$agent ? false : User::findFirst([
            'conditions' => 'id = :id: AND deleted = 0',
            'bind' => ['id' => (string) $agent->userId],
        ]);
        $scopes = $agent ? AgentDelegationScopes::decode($agent->delegationScopes) : null;

        if (!$user
            || !$user->isAgent()
            || !AclLockoutGuard::isAuthenticatableAccountStatus($user->accountStatus)
            || !$scopes
            || !(new WorkspaceRuntime())->serves((string) $runtime->id, $agent->workspaceId)
        ) {
            throw new UnAuthorized(self::AGENT_NOT_DELEGABLE_ERROR);
        }

        return ['agent' => $agent, 'user' => $user, 'scopes' => $scopes];
    }
}
