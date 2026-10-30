<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use Gaia\MVC\Models\Workflow;
use Gaia\MVC\Models\Workflownode;

/**
 * Reads the trigger nodes of Active workflows: for a trigger kind, each
 * live node of that kind with its config and its workflow's scope.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActiveTriggerNodes
{
    /**
     * The trigger nodes of this kind in Active workflows.
     *
     * @param  string $kind A trigger kind, e.g. `issue.created` or `schedule`.
     * @return array[] Each: workflowId, nodeId, config (array), scope (project ids).
     */
    public function ofKind($kind)
    {
        $rows = \Phalcon\Di::getDefault()->get('modelsManager')->createBuilder()
            ->columns([
                'Node.id as nodeId',
                'Node.workflowId as workflowId',
                'Node.config as config',
                'Workflow.scope as scope',
            ])
            ->from(['Node' => Workflownode::class])
            ->innerJoin(Workflow::class, 'Workflow.id = Node.workflowId', 'Workflow')
            ->where('Node.category = :category:', ['category' => NodeKindCatalog::TRIGGER])
            ->andWhere('Node.kind = :kind:', ['kind' => (string) $kind])
            ->andWhere('Node.deleted = false')
            ->andWhere('Workflow.deleted = false')
            ->andWhere('Workflow.status = :status:', ['status' => WorkflowStatus::ACTIVE])
            ->getQuery()
            ->execute();

        $nodes = [];
        foreach ($rows as $row) {
            $config = WorkflowGraphReader::decodeConfig($row->config);
            $nodes[] = [
                'workflowId' => (string) $row->workflowId,
                'nodeId' => (string) $row->nodeId,
                'config' => is_array($config) ? $config : [],
                'scope' => WorkflowScope::decode($row->scope),
            ];
        }

        return $nodes;
    }
}
