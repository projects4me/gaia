<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Runs a workflow for one event: walks its graph from the trigger node,
 * evaluates each condition, carries out each action and writes every step
 * to the run history. The run ends at an `end` node or at a port with no
 * edge (completed), or at the first step that fails (failed).
 *
 * Actions are not checked against anyone's ACL: whoever may create a
 * workflow decides what it does. Changes to existing records are made as
 * the workflow's owner (or whoever created it); records a step creates
 * belong to the project member the step names.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowRunner implements WorkflowEngine
{
    /**
     * The most steps one run takes, so a cycle in the graph ends.
     */
    public const MAX_STEPS = 100;

    /**
     * @var WorkflowGraphReader
     */
    private $graph;

    /**
     * @var RunJournal
     */
    private $journal;

    /**
     * @var ActionCatalog
     */
    private $actions;

    /**
     * @var ConditionEvaluator
     */
    private $conditions;

    /**
     * @var ExpressionResolver
     */
    private $expressions;

    /**
     * @var LoopGuard
     */
    private $guard;

    /**
     * @var WorkflowChangeEvents|null
     */
    private $events;

    /**
     * @param WorkflowChangeEvents|null $events  Raises the events of the run's changes; none when null.
     * @param WorkflowGraphReader|null  $graph
     * @param RunJournal|null           $journal
     * @param ActionCatalog|null        $actions
     * @param UserSelector|null         $users
     */
    public function __construct(
        WorkflowChangeEvents $events = null,
        WorkflowGraphReader $graph = null,
        RunJournal $journal = null,
        ActionCatalog $actions = null,
        UserSelector $users = null
    ) {
        $users = $users ?: new UserSelector();
        $this->expressions = new ExpressionResolver($users->functions());
        $this->conditions = new ConditionEvaluator($this->expressions);
        $this->events = $events;
        $this->graph = $graph ?: new WorkflowGraphReader();
        $this->journal = $journal ?: new RunJournal();
        $this->actions = $actions ?: new ActionCatalog(new WorkflowRecords(), $users, $this->expressions);
        $this->guard = new LoopGuard();
    }

    /**
     * {@inheritdoc}
     */
    public function start($workflowId, $triggerNodeId, array $payload)
    {
        $refusal = $this->guard->refusal($workflowId, $payload);
        if ($refusal !== null) {
            $this->log('info', "Workflow {$workflowId} not started: {$refusal}.");
            return;
        }

        $workflow = $this->graph->workflow($workflowId);
        if ($workflow === null || $workflow['status'] !== WorkflowStatus::ACTIVE) {
            return;
        }

        $runId = $this->journal->start([
            'workflowId' => $workflow['id'],
            'workflowVersion' => $workflow['version'],
            'triggerNodeId' => $triggerNodeId,
            'eventName' => isset($payload['eventName']) ? $payload['eventName'] : null,
            'eventId' => isset($payload['eventId']) ? $payload['eventId'] : null,
            'projectId' => isset($payload['projectId']) ? $payload['projectId'] : null,
            'parentRunId' => LoopGuard::parentRunId($payload),
            'depth' => LoopGuard::depth($payload),
            'inputs' => $payload,
        ]);
        $context = new RunContext(
            $runId,
            ['id' => $workflow['id'], 'name' => $workflow['name'], 'version' => $workflow['version']],
            $workflow['ownerId'] ?: $workflow['createdUser'],
            $payload
        );

        try {
            list($status, $error) = $this->walk((string) $triggerNodeId, $context);
        } catch (\Throwable $exception) {
            // A step the journal could not record must not leave the run running.
            list($status, $error) = [RunStatus::FAILED, $exception->getMessage()];
        }
        $this->journal->finish($runId, $status, $error);
    }

    /**
     * Takes the steps from the trigger node on.
     *
     * @param  string     $nodeId
     * @param  RunContext $context
     * @return array [run status, error]
     */
    private function walk($nodeId, RunContext $context)
    {
        $nodes = [];
        foreach ($this->graph->nodes($context->workflow['id']) as $node) {
            $nodes[$node['id']] = $node;
        }
        $next = [];
        foreach ($this->graph->edges($context->workflow['id']) as $edge) {
            $next[$edge['fromNodeId']][$edge['fromPort']] = $edge['toNodeId'];
        }

        for ($sequence = 1; $sequence <= self::MAX_STEPS; $sequence++) {
            if (!isset($nodes[$nodeId])) {
                return [RunStatus::FAILED, "Node {$nodeId} is not in the workflow."];
            }

            $step = $this->step($nodes[$nodeId], $context, $sequence);
            $this->journal->step($context->runId, $step);
            $this->raiseChanges($context);

            if ($step['status'] === RunStatus::FAILED) {
                return [RunStatus::FAILED, $step['error']];
            }
            if ($step['port'] === null || !isset($next[$nodeId][$step['port']])) {
                return [RunStatus::COMPLETED, null];
            }
            $nodeId = $next[$nodeId][$step['port']];
        }

        return [RunStatus::FAILED, 'The run took more than ' . self::MAX_STEPS . ' steps.'];
    }

    /**
     * Carries out one node and describes the step.
     *
     * @param  array      $node
     * @param  RunContext $context
     * @param  int        $sequence
     * @return array
     */
    private function step(array $node, RunContext $context, $sequence)
    {
        $started = microtime(true);
        $startedAt = RunJournal::now();
        $port = null;
        $outputs = [];
        $error = null;

        try {
            list($port, $outputs) = $this->carryOut($node, $context);
        } catch (WorkflowStepException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $this->log('error', "Workflow {$context->workflow['id']} step {$node['id']} failed: {$error}");
        }

        return [
            'sequence' => $sequence,
            'nodeId' => $node['id'],
            'category' => $node['category'],
            'kind' => $node['kind'],
            'status' => $error === null ? RunStatus::COMPLETED : RunStatus::FAILED,
            'port' => $error === null ? $port : null,
            'inputs' => $this->inputs($node['config'], $context),
            'outputs' => $outputs,
            'error' => $error,
            'startedAt' => $startedAt,
            'finishedAt' => RunJournal::now(),
            'durationMs' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /**
     * @param  array      $node
     * @param  RunContext $context
     * @return array [port taken (null ends the run), outputs]
     * @throws WorkflowStepException
     */
    private function carryOut(array $node, RunContext $context)
    {
        switch ($node['category']) {
            case NodeKindCatalog::TRIGGER:
                return ['next', []];
            case NodeKindCatalog::CONDITION:
                $port = $node['kind'] === 'switch'
                    ? $this->conditions->switchPort($node['config'], $context->values())
                    : $this->conditions->filter($node['config'], $context->values());
                return [$port, []];
            case NodeKindCatalog::ACTION:
                if ($node['kind'] === NodeKindCatalog::END) {
                    return [null, []];
                }
                return ['next', $this->actions->action($node['kind'])->run($node['config'], $context)];
        }

        throw new WorkflowStepException("{$node['category']} step {$node['kind']} cannot run yet.");
    }

    /**
     * The node's settings with their expressions resolved, as far as they resolve.
     *
     * @param  array      $config
     * @param  RunContext $context
     * @return array
     */
    private function inputs(array $config, RunContext $context)
    {
        $inputs = [];
        foreach ($config as $name => $setting) {
            try {
                $inputs[$name] = $this->expressions->resolve($setting, $context->values());
            } catch (\Throwable $e) {
                $inputs[$name] = $setting;
            }
        }

        return $inputs;
    }

    /**
     * Raises the events of the records the last step changed. A failure
     * here does not fail the run.
     *
     * @param  RunContext $context
     * @return void
     */
    private function raiseChanges(RunContext $context)
    {
        foreach ($context->takeChanges() as list($record, $on)) {
            if ($this->events === null) {
                continue;
            }
            try {
                $this->events->changed($record, $on, $this->guard->carried($context));
            } catch (\Throwable $e) {
                $this->log('error', 'Workflow change events failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * @param  string $level
     * @param  string $message
     * @return void
     */
    private function log($level, $message)
    {
        global $logger;

        if ($logger) {
            $logger->$level($message);
        }
    }
}
