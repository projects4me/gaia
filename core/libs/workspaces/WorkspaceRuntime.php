<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\Libraries\Authorization\ServiceClients;
use Gaia\MVC\Models\Serviceclient;
use Gaia\MVC\Models\Workspace;

/**
 * The rule that a workspace is bound to one runtime, and a runtime acts only
 * for the agents of the workspaces bound to it (RBAC D-073, Workspaces D-009).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceRuntime
{
    /**
     * The service client kind a workspace may be bound to.
     */
    const KIND = 'runtime';

    const NOT_A_RUNTIME = 'runtimeId must be the id of an active service client of kind runtime.';

    /**
     * Why a runtime cannot be bound to a workspace, or null when it can: it
     * must be a live, active service client of kind runtime. An empty value
     * (no binding) is always allowed.
     *
     * @param  mixed $runtimeId
     * @return string|null
     */
    public function bindingError($runtimeId)
    {
        if ($runtimeId === null || (string) $runtimeId === '') {
            return null;
        }

        $client = $this->findServiceClient((string) $runtimeId);
        if (!$client
            || (string) $client->kind !== self::KIND
            || (string) $client->status !== ServiceClients::STATUS_ACTIVE
        ) {
            return self::NOT_A_RUNTIME;
        }

        return null;
    }

    /**
     * Whether the save sets or changes the binding, so it must be checked;
     * an unchanged binding to a since disabled client is kept.
     *
     * @param  array|null $snapshot  The stored row, null for a new record
     * @param  mixed      $runtimeId The value being saved
     * @return bool
     */
    public function isChange($snapshot, $runtimeId)
    {
        $stored = is_array($snapshot) && isset($snapshot['runtimeId']) ? (string) $snapshot['runtimeId'] : '';

        return $stored !== (string) $runtimeId;
    }

    /**
     * Whether the service client serves the workspace: the workspace is live
     * and bound to it. A missing workspace or binding serves no one.
     *
     * @param  string      $serviceClientId The Serviceclient record id
     * @param  string|null $workspaceId
     * @return bool
     */
    public function serves($serviceClientId, $workspaceId)
    {
        if ((string) $serviceClientId === '' || $workspaceId === null || (string) $workspaceId === '') {
            return false;
        }

        $workspace = $this->findWorkspace((string) $workspaceId);

        return $workspace && (string) $workspace->runtimeId === (string) $serviceClientId;
    }

    /**
     * The live service client with the record id, or null.
     *
     * @param  string $id
     * @return Serviceclient|null
     */
    protected function findServiceClient($id)
    {
        return Serviceclient::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => $id],
        ]) ?: null;
    }

    /**
     * The live workspace with the id, or null.
     *
     * @param  string $id
     * @return Workspace|null
     */
    protected function findWorkspace($id)
    {
        return Workspace::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => $id],
        ]) ?: null;
    }
}
