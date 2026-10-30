<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use Gaia\MVC\Models\Workflow;
use Gaia\MVC\Models\Workflowedge;
use Gaia\MVC\Models\Workflownode;

/**
 * Reads a stored workflow's status and its live nodes and edges in the
 * shape `WorkflowGraph` checks.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowGraphReader
{
    /**
     * The workflow's status, or null when there is no live workflow with this id.
     *
     * @param  string $workflowId
     * @return string|null
     */
    public function status($workflowId)
    {
        $workflow = Workflow::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $workflowId],
        ]);

        return $workflow ? (string) $workflow->status : null;
    }

    /**
     * A live workflow's name, status, version and users, or null.
     *
     * @param  string $workflowId
     * @return array|null
     */
    public function workflow($workflowId)
    {
        $workflow = Workflow::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $workflowId],
        ]);
        if (!$workflow) {
            return null;
        }

        return [
            'id' => (string) $workflow->id,
            'name' => (string) $workflow->name,
            'status' => (string) $workflow->status,
            'version' => (int) $workflow->version,
            'ownerId' => $workflow->ownerId ? (string) $workflow->ownerId : null,
            'createdUser' => (string) $workflow->createdUser,
        ];
    }

    /**
     * The workflow's live nodes.
     *
     * @param  string $workflowId
     * @return array
     */
    public function nodes($workflowId)
    {
        $nodes = [];
        foreach ($this->live(Workflownode::class, $workflowId) as $row) {
            $config = self::decodeConfig($row->config);
            $nodes[] = [
                'id' => (string) $row->id,
                'name' => $row->name,
                'category' => (string) $row->category,
                'kind' => (string) $row->kind,
                'config' => is_array($config) ? $config : [],
            ];
        }

        return $nodes;
    }

    /**
     * The workflow's live edges.
     *
     * @param  string $workflowId
     * @return array
     */
    public function edges($workflowId)
    {
        $edges = [];
        foreach ($this->live(Workflowedge::class, $workflowId) as $row) {
            $edges[] = [
                'id' => (string) $row->id,
                'fromNodeId' => (string) $row->fromNodeId,
                'fromPort' => (string) $row->fromPort,
                'toNodeId' => (string) $row->toNodeId,
            ];
        }

        return $edges;
    }

    /**
     * A node config as stored (JSON text) or as sent (an object), as an
     * array; null when it is not a JSON object. Empty means no settings.
     *
     * @param  mixed $value
     * @return array|null
     */
    public static function decodeConfig($value)
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || trim((string) $value) === '') {
            return [];
        }
        $decoded = json_decode((string) $value, true);
        $isObject = is_array($decoded) && (substr(ltrim((string) $value), 0, 1) === '{' || $decoded === []);

        return $isObject ? $decoded : null;
    }

    /**
     * @param  string $modelClass
     * @param  string $workflowId
     * @return iterable
     */
    private function live($modelClass, $workflowId)
    {
        return $modelClass::find([
            'conditions' => 'workflowId = :workflowId: AND deleted = false',
            'bind' => ['workflowId' => (string) $workflowId],
        ]);
    }
}
