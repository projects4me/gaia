<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Runs a workflow for one event: walks its graph from the trigger node,
 * evaluates each condition, carries out each action and writes every step
 * to the run history. The run ends at an `end` node or at a port with no
 * edge (completed), or at the first step that fails (failed). At an actor
 * step it waits: the actor's answer (an agent's report, a user's reply, a
 * timeout) resumes it on the port the answer names.
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
class WorkflowRunner implements WorkflowEngine, WaitingStepResumer
{
    /**
     * The most steps one run takes, so a cycle in the graph ends.
     */
    public const MAX_STEPS = WorkflowWalk::MAX_STEPS;

    /**
     * @var WorkflowGraphReader
     */
    private $graph;

    /**
     * @var WorkflowWalk
     */
    private $walk;

    /**
     * @var RunJournal
     */
    private $journal;

    /**
     * @var ActionCatalog
     */
    private $actions;

    /**
     * @var ActorCatalog
     */
    private $actors;

    /**
     * @var AgentRunRequests|null
     */
    private $requests;

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
     * @param ActorCatalog|null         $actors
     * @param AgentRunRequests|null     $requests Tells agents' runtimes about their steps; none when null.
     */
    public function __construct(
        WorkflowChangeEvents $events = null,
        WorkflowGraphReader $graph = null,
        RunJournal $journal = null,
        ActionCatalog $actions = null,
        UserSelector $users = null,
        ActorCatalog $actors = null,
        AgentRunRequests $requests = null
    ) {
        $users = $users ?: new UserSelector();
        $this->expressions = new ExpressionResolver($users->functions());
        $this->conditions = new ConditionEvaluator($this->expressions);
        $this->events = $events;
        $this->graph = $graph ?: new WorkflowGraphReader();
        $this->walk = new WorkflowWalk($this->graph);
        $this->journal = $journal ?: new RunJournal();
        $this->actions = $actions ?: new ActionCatalog(new WorkflowRecords(), $users, $this->expressions);
        $this->actors = $actors
            ?: new ActorCatalog(new WorkflowRecords(), $users, $this->expressions, new ActorSteps());
        $this->requests = $requests;
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

        $resource = isset($payload['resource']) && is_array($payload['resource']) ? $payload['resource'] : [];
        try {
            $runId = $this->journal->start([
                'workflowId' => $workflow['id'],
                'workflowVersion' => $workflow['version'],
                'triggerNodeId' => $triggerNodeId,
                'eventName' => isset($payload['eventName']) ? $payload['eventName'] : null,
                'eventId' => isset($payload['eventId']) ? $payload['eventId'] : null,
                'entityType' => isset($resource['type']) ? $resource['type'] : null,
                'entityId' => isset($resource['id']) ? $resource['id'] : null,
                'projectId' => isset($payload['projectId']) ? $payload['projectId'] : null,
                'parentRunId' => LoopGuard::parentRunId($payload),
                'depth' => LoopGuard::depth($payload),
                'inputs' => $payload,
            ]);
        } catch (\Throwable $exception) {
            // A run that cannot be journaled is not started, and the caller
            // (an event or the scheduler) goes on with the other workflows.
            $this->log('error', "Workflow {$workflowId} not started: the journal failed: {$exception->getMessage()}");
            return;
        }
        $context = new RunContext(
            $runId,
            ['id' => $workflow['id'], 'name' => $workflow['name'], 'version' => $workflow['version']],
            $workflow['ownerId'] ?: $workflow['createdUser'],
            $payload
        );

        $this->carryOn($context, function () use ($triggerNodeId, $context) {
            return $this->walk((string) $triggerNodeId, $context, 1);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function resume($stepId, $port, array $outputs)
    {
        try {
            $waiting = $this->journal->waitingStep($stepId);
        } catch (\Throwable $exception) {
            // The answer is saved with the actor step already; the caller's write must not fail.
            $this->log('error', "Workflow step {$stepId} could not be resumed: {$exception->getMessage()}");
            return;
        }
        if ($waiting === null) {
            $this->log('info', "Workflow step {$stepId} is not waiting; its answer is ignored.");
            return;
        }
        $run = $waiting['run'];
        $step = $waiting['step'];

        $payload = json_decode((string) $run['inputs'], true);
        $payload = is_array($payload) ? $payload : [];
        $payload['step'] = $outputs;
        $workflow = $this->graph->workflow($run['workflowId']);
        $context = new RunContext(
            $run['id'],
            [
                'id' => $run['workflowId'],
                'name' => $workflow === null ? null : $workflow['name'],
                'version' => (int) $run['workflowVersion'],
            ],
            $workflow === null ? '' : ($workflow['ownerId'] ?: $workflow['createdUser']),
            $payload
        );

        try {
            $answered = $this->journal->answer($step['id'], $port, $outputs);
        } catch (\Throwable $exception) {
            $this->log('error', "Workflow step {$stepId} could not be answered: {$exception->getMessage()}");
            return;
        }
        if (!$answered) {
            // Another answer took the step first.
            return;
        }
        $this->carryOn($context, function () use ($workflow, $step, $port, $context) {
            if ($workflow === null || $workflow['status'] !== WorkflowStatus::ACTIVE) {
                return [RunStatus::FAILED, 'The workflow is no longer active.'];
            }
            $nodeId = $this->walk->next($context->workflow['id'], $step['nodeId'], $port);
            if ($nodeId === null) {
                return [RunStatus::COMPLETED, null];
            }

            return $this->walk(
                $nodeId,
                $context,
                $this->journal->lastSequence($context->runId) + 1
            );
        });
    }

    /**
     * Takes the run's next steps and records where they leave it.
     *
     * @param  RunContext $context
     * @param  callable   $steps Returns [run status, error].
     * @return void
     */
    private function carryOn(RunContext $context, callable $steps)
    {
        try {
            list($status, $error) = $steps();
        } catch (\Throwable $exception) {
            // A step the journal could not record must not leave the run running.
            list($status, $error) = [RunStatus::FAILED, $exception->getMessage()];
        }
        try {
            $this->journal->finish($context->runId, $status, $error);
        } catch (\Throwable $exception) {
            $this->log(
                'error',
                "Workflow {$context->workflow['id']} run {$context->runId} could not be finished in the journal: "
                . $exception->getMessage()
            );
        }
        $this->requestAgents($context, $status);
    }

    /**
     * Tells the agents' runtimes about the run's 'Run agent' step, now that
     * the run is saved as waiting for it. A failed run tells nobody.
     *
     * @param  RunContext $context
     * @param  string     $status
     * @return void
     */
    private function requestAgents(RunContext $context, $status)
    {
        foreach ($context->takeAgentRequests() as $step) {
            if ($this->requests === null || $status !== RunStatus::WAITING) {
                continue;
            }
            try {
                $this->requests->requested($step);
            } catch (\Throwable $exception) {
                $this->log('error', "Agent run request for step {$step['id']} failed: {$exception->getMessage()}");
            }
        }
    }

    /**
     * Takes the steps from a node on, carrying each one out and journaling it.
     *
     * @param  string     $nodeId
     * @param  RunContext $context
     * @param  int        $first   The sequence number of the first step.
     * @return array [run status, error]
     */
    private function walk($nodeId, RunContext $context, $first)
    {
        return $this->walk->from($context->workflow['id'], $nodeId, $first, function (array $node, $sequence) use ($context) {
            $step = $this->step($node, $context, $sequence);
            try {
                $this->journal->step($context->runId, $step);
            } finally {
                // The step's changes are saved already, so their events go out
                // even when the journal cannot record the step.
                $this->raiseChanges($context);
            }

            return $step;
        });
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
        $context->step = ['id' => create_guid(), 'nodeId' => (string) $node['id']];
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

        if ($error !== null) {
            $status = RunStatus::FAILED;
        } else {
            $status = $node['category'] === NodeKindCatalog::ACTOR ? RunStatus::WAITING : RunStatus::COMPLETED;
        }

        return [
            'id' => $context->step['id'],
            'sequence' => $sequence,
            'nodeId' => $node['id'],
            'category' => $node['category'],
            'kind' => $node['kind'],
            'status' => $status,
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
     * @return array [port taken (null ends the run, or for an actor waits), outputs]
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
            case NodeKindCatalog::ACTOR:
                return [null, $this->actors->actor($node['kind'])->run($node['config'], $context)];
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
