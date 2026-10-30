<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Walks a workflow from a trigger node for a payload without changing anything:
 * triggers and conditions are evaluated, actions and actor steps are only
 * simulated (their settings are resolved, nothing is written, no agent runs).
 * The result is the path taken and each step's resolved inputs. Nothing is
 * saved to the run history.
 *
 * A simulated actor step goes on by the port the caller names for its node,
 * or else by its first port.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowTestRun
{
    /**
     * The status of an action or actor step that was simulated.
     */
    public const SIMULATED = 'simulated';

    /**
     * @var WorkflowGraphReader
     */
    private $graph;

    /**
     * @var NodeKindCatalog
     */
    private $kinds;

    /**
     * @var ExpressionResolver
     */
    private $expressions;

    /**
     * @var ConditionEvaluator
     */
    private $conditions;

    /**
     * @var WorkflowWalk
     */
    private $walk;

    /**
     * @param WorkflowGraphReader|null $graph
     * @param NodeKindCatalog|null     $kinds
     * @param ExpressionResolver|null  $expressions
     * @param ConditionEvaluator|null  $conditions
     */
    public function __construct(
        WorkflowGraphReader $graph = null,
        NodeKindCatalog $kinds = null,
        ExpressionResolver $expressions = null,
        ConditionEvaluator $conditions = null
    ) {
        $this->graph = $graph ?: new WorkflowGraphReader();
        $this->walk = new WorkflowWalk($this->graph);
        $this->kinds = $kinds ?: new NodeKindCatalog();
        $this->expressions = $expressions ?: new ExpressionResolver((new UserSelector())->functions());
        $this->conditions = $conditions ?: new ConditionEvaluator($this->expressions);
    }

    /**
     * The trigger node to test from: the one named, or the workflow's only one.
     *
     * @param  string      $workflowId
     * @param  string|null $nodeId
     * @return array The node: id, kind, config.
     * @throws TestRunRefusal
     */
    public function trigger($workflowId, $nodeId = null)
    {
        if ($this->graph->workflow($workflowId) === null) {
            throw new TestRunRefusal("Workflow {$workflowId} does not exist.", 404);
        }
        $triggers = [];
        foreach ($this->graph->nodes($workflowId) as $node) {
            if ($node['category'] === NodeKindCatalog::TRIGGER) {
                $triggers[$node['id']] = $node;
            }
        }
        if ($nodeId !== null && $nodeId !== '') {
            if (!isset($triggers[$nodeId])) {
                throw new TestRunRefusal("Workflow {$workflowId} has no trigger {$nodeId}.", 404);
            }

            return $triggers[$nodeId];
        }
        if (count($triggers) !== 1) {
            throw new TestRunRefusal(
                count($triggers) === 0
                    ? "Workflow {$workflowId} has no trigger."
                    : 'The workflow has several triggers: send triggerNodeId.',
                422
            );
        }

        return reset($triggers);
    }

    /**
     * Simulates a run.
     *
     * @param  string $workflowId
     * @param  string $triggerNodeId
     * @param  array  $payload      The trigger payload (see TestRunPayload).
     * @param  array  $actorPorts   Actor node id => the port its simulated step goes on by.
     * @return array workflowId, workflowVersion, triggerNodeId, status, error, path, inputs, steps
     * @throws TestRunRefusal When the workflow's scope does not cover the payload's project.
     */
    public function run($workflowId, $triggerNodeId, array $payload, array $actorPorts = [])
    {
        $workflow = $this->graph->workflow($workflowId);
        $projectId = isset($payload['projectId']) ? $payload['projectId'] : null;
        // As TriggerDispatcher: a workflow only runs for the projects of its scope.
        if (!WorkflowScope::covers(isset($workflow['scope']) ? $workflow['scope'] : [], $projectId)) {
            throw new TestRunRefusal(
                $projectId === null || $projectId === ''
                    ? 'The event has no project, so the workflow would not run for it.'
                    : "The workflow's scope does not include project {$projectId}, so it would not run for it.",
                422
            );
        }
        $context = new RunContext(
            create_guid(),
            ['id' => $workflow['id'], 'name' => $workflow['name'], 'version' => $workflow['version']],
            $workflow['ownerId'] ?: $workflow['createdUser'],
            $payload
        );

        $steps = [];
        list($status, $error) = $this->walk->from(
            $workflow['id'],
            (string) $triggerNodeId,
            1,
            function (array $node, $sequence) use ($context, $actorPorts, &$steps) {
                return $steps[] = $this->step($node, $context, $sequence, $actorPorts);
            }
        );

        return [
            'workflowId' => $workflow['id'],
            'workflowVersion' => $workflow['version'],
            'triggerNodeId' => (string) $triggerNodeId,
            'status' => $status,
            'error' => $error,
            'path' => array_column($steps, 'nodeId'),
            'inputs' => $payload,
            'steps' => $steps,
        ];
    }

    /**
     * Takes one node: evaluates a trigger or condition, simulates the rest.
     *
     * @param  array      $node
     * @param  RunContext $context
     * @param  int        $sequence
     * @param  array      $actorPorts
     * @return array
     */
    private function step(array $node, RunContext $context, $sequence, array $actorPorts)
    {
        $step = [
            'sequence' => $sequence,
            'nodeId' => $node['id'],
            'category' => $node['category'],
            'kind' => $node['kind'],
            'status' => RunStatus::COMPLETED,
            'port' => null,
            'inputs' => [],
            'outputs' => [],
            'error' => null,
        ];

        try {
            $step['inputs'] = $this->inputs($node['config'], $context, $node['category'] !== NodeKindCatalog::CONDITION);
            switch ($node['category']) {
                case NodeKindCatalog::TRIGGER:
                    $step['port'] = 'next';
                    break;
                case NodeKindCatalog::CONDITION:
                    $step['port'] = $node['kind'] === 'switch'
                        ? $this->conditions->switchPort($node['config'], $context->values())
                        : $this->conditions->filter($node['config'], $context->values());
                    break;
                case NodeKindCatalog::ACTOR:
                    $step['status'] = self::SIMULATED;
                    $step['port'] = $this->actorPort($node, $actorPorts);
                    break;
                default:
                    $step['status'] = self::SIMULATED;
                    $step['port'] = $node['kind'] === NodeKindCatalog::END ? null : 'next';
            }
        } catch (\Throwable $e) {
            $step['status'] = RunStatus::FAILED;
            $step['port'] = null;
            $step['error'] = $e->getMessage();
        }

        return $step;
    }

    /**
     * @param  array $node
     * @param  array $actorPorts
     * @return string|null
     * @throws WorkflowStepException When the named port is not one of the node's.
     */
    private function actorPort(array $node, array $actorPorts)
    {
        $ports = $this->kinds->ports($node['category'], $node['kind'], $node['config']);
        if (isset($actorPorts[$node['id']])) {
            if (!in_array($actorPorts[$node['id']], $ports, true)) {
                throw new WorkflowStepException("The step has no port {$actorPorts[$node['id']]}.");
            }

            return (string) $actorPorts[$node['id']];
        }

        return $ports ? $ports[0] : null;
    }

    /**
     * The node's settings with their expressions resolved. A setting that
     * does not resolve fails the step, as it would in a real run; a
     * condition's settings are evaluated by the condition itself, so they
     * stay as written when they do not resolve.
     *
     * @param  array      $config
     * @param  RunContext $context
     * @param  bool       $strict  Whether an unresolved setting fails the step.
     * @return array
     */
    private function inputs(array $config, RunContext $context, $strict)
    {
        $inputs = [];
        foreach ($config as $name => $setting) {
            try {
                $inputs[$name] = $this->expressions->resolve($setting, $context->values());
            } catch (\Throwable $e) {
                if ($strict) {
                    throw $e;
                }
                $inputs[$name] = $setting;
            }
        }

        return $inputs;
    }
}
