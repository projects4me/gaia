<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Follows a workflow's edges from a node: takes a step at each node and goes
 * on by the port the step chose, until a step fails, waits, ends the run or
 * the run is too long. What a step does (carry the node out or only simulate
 * it) is the caller's, so a real run and a test run take the same path.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowWalk
{
    /**
     * The most steps a run takes before it is failed as a loop.
     */
    public const MAX_STEPS = 100;

    /**
     * @var WorkflowGraphReader
     */
    private $graph;

    /**
     * @param WorkflowGraphReader|null $graph
     */
    public function __construct(WorkflowGraphReader $graph = null)
    {
        $this->graph = $graph ?: new WorkflowGraphReader();
    }

    /**
     * Takes the steps from a node on.
     *
     * @param  string   $workflowId
     * @param  string   $nodeId
     * @param  int      $first      The sequence number of the first step.
     * @param  callable $take       fn(array $node, int $sequence): array the step (status, port, error).
     * @return array [run status, error]
     */
    public function from($workflowId, $nodeId, $first, callable $take)
    {
        list($nodes, $next) = $this->graphOf($workflowId);

        for ($sequence = $first; $sequence < $first + self::MAX_STEPS; $sequence++) {
            if (!isset($nodes[$nodeId])) {
                return [RunStatus::FAILED, "Node {$nodeId} is not in the workflow."];
            }

            $step = $take($nodes[$nodeId], $sequence);

            if ($step['status'] === RunStatus::FAILED) {
                return [RunStatus::FAILED, $step['error']];
            }
            if ($step['status'] === RunStatus::WAITING) {
                return [RunStatus::WAITING, null];
            }
            if ($step['port'] === null || !isset($next[$nodeId][$step['port']])) {
                return [RunStatus::COMPLETED, null];
            }
            $nodeId = $next[$nodeId][$step['port']];
        }

        return [RunStatus::FAILED, 'The run took more than ' . self::MAX_STEPS . ' steps.'];
    }

    /**
     * The node a port of a node leads to.
     *
     * @param  string $workflowId
     * @param  string $nodeId
     * @param  string $port
     * @return string|null Null when the port leads nowhere.
     */
    public function next($workflowId, $nodeId, $port)
    {
        list(, $next) = $this->graphOf($workflowId);

        return isset($next[$nodeId][$port]) ? $next[$nodeId][$port] : null;
    }

    /**
     * The workflow's nodes by id and the node each port leads to.
     *
     * @param  string $workflowId
     * @return array [nodes, next]
     */
    private function graphOf($workflowId)
    {
        $nodes = [];
        foreach ($this->graph->nodes($workflowId) as $node) {
            $nodes[$node['id']] = $node;
        }
        $next = [];
        foreach ($this->graph->edges($workflowId) as $edge) {
            $next[$edge['fromNodeId']][$edge['fromPort']] = $edge['toNodeId'];
        }

        return [$nodes, $next];
    }
}
