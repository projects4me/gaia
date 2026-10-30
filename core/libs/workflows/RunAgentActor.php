<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The `run_agent` actor: records a pending step for the agent (the prompt
 * with its expressions resolved, session, model rule, max turns and the
 * deadlines) and asks for the agent's runtime to be told once the run is
 * saved as waiting (see WorkflowRunner). The run waits until
 * the runtime reports an outcome (see ActorStepReports) or the step times
 * out (see ActorStepTimeouts); the outcome is the port the run goes on by.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RunAgentActor extends WorkflowAction
{
    public const KIND = 'run_agent';

    /**
     * The outcomes a runtime reports, one port each.
     */
    public const OUTCOMES = ['done', 'needs_input', 'blocked', 'failed', 'limit'];

    /**
     * The port a step that times out takes.
     */
    public const TIMEOUT_OUTCOME = 'failed';

    /**
     * How a run's session starts: a new one, or the agent's last one on the record.
     */
    public const SESSIONS = ['new', 'resume'];

    /**
     * Minutes the runtime has to claim the step, unless config.claimTimeout says otherwise.
     */
    public const CLAIM_MINUTES = 15;

    /**
     * Minutes from the request to the report, unless config.timeout says otherwise.
     */
    public const FINISH_MINUTES = 240;

    /**
     * @var ActorSteps
     */
    private $steps;

    /**
     * @param WorkflowRecords    $records
     * @param UserSelector       $users
     * @param ExpressionResolver $expressions
     * @param ActorSteps         $steps
     */
    public function __construct(
        WorkflowRecords $records,
        UserSelector $users,
        ExpressionResolver $expressions,
        ActorSteps $steps
    ) {
        parent::__construct($records, $users, $expressions);
        $this->steps = $steps;
    }

    /**
     * {@inheritdoc}
     */
    public function run(array $config, RunContext $context)
    {
        $agentId = self::text($this->setting($config, 'agentId', $context), 'agentId');
        $agent = $this->records->find('Agent', $agentId);
        if ($agent === null) {
            throw new WorkflowStepException("Agent {$agentId} no longer exists.");
        }

        $session = (string) $this->setting($config, 'session', $context, 'new');
        if (!in_array($session, self::SESSIONS, true)) {
            throw new WorkflowStepException('config.session must be new or resume.');
        }
        $model = $this->setting($config, 'model', $context);
        $claimMinutes = self::positive(
            $this->setting($config, 'claimTimeout', $context, self::CLAIM_MINUTES),
            'claimTimeout'
        );
        $finishMinutes = self::positive($this->setting($config, 'timeout', $context, self::FINISH_MINUTES), 'timeout');
        if ($claimMinutes > $finishMinutes) {
            throw new WorkflowStepException('config.claimTimeout must not be longer than config.timeout.');
        }
        $maxTurns = $this->setting($config, 'maxTurns', $context, $agent->readAttribute('maxTurns'));

        list($entityType, $entityId) = $this->entity($context);
        $now = time();
        $step = [
            'id' => $context->step['id'],
            'runId' => $context->runId,
            'workflowId' => (string) $context->workflow['id'],
            'nodeId' => $context->step['nodeId'],
            'kind' => self::KIND,
            'actorUserId' => (string) $agent->readAttribute('userId'),
            'agentId' => $agentId,
            'projectId' => $context->projectId(),
            'entityType' => $entityType,
            'entityId' => $entityId,
            'prompt' => (string) $this->setting($config, 'prompt', $context, ''),
            'session' => $session,
            'model' => is_scalar($model) && trim((string) $model) !== '' ? trim((string) $model) : null,
            'maxTurns' => in_array($maxTurns, [null, '', 0, '0'], true) ? null : self::positive($maxTurns, 'maxTurns'),
            'claimBy' => gmdate('Y-m-d H:i:s', $now + $claimMinutes * 60),
            'finishBy' => gmdate('Y-m-d H:i:s', $now + $finishMinutes * 60),
        ];
        $this->steps->create($step);
        $context->requestAgent($step);

        return [
            'agentId' => $agentId,
            'actorUserId' => $step['actorUserId'],
            'claimBy' => $step['claimBy'],
            'finishBy' => $step['finishBy'],
        ];
    }

    /**
     * The record the run is about, as [entityType, entityId]; nulls when none.
     *
     * @param  RunContext $context
     * @return array
     */
    private function entity(RunContext $context)
    {
        $subject = $context->subject();

        return $subject === null ? [null, null] : [strtolower($subject[0]), $subject[1]];
    }

    /**
     * @param  mixed  $value
     * @param  string $name
     * @return int
     * @throws WorkflowStepException When it is not a whole number above zero.
     */
    private static function positive($value, $name)
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }
        if (is_string($value) && ctype_digit(trim($value)) && (int) $value > 0) {
            return (int) $value;
        }

        throw new WorkflowStepException("config.{$name} must be a whole number above zero.");
    }
}
