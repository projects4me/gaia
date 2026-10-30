<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Times out the 'Run agent' steps not claimed or not finished in time: each
 * one takes the 'failed' output. Also takes on the runs whose actor step
 * was answered but whose resume did not happen (the process died, the
 * database failed): their answer is handed to the engine again. Run every
 * minute (`php cli.php workflow timeouts`).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActorStepTimeouts
{
    /**
     * @var ActorSteps
     */
    private $steps;

    /**
     * @var WaitingStepResumer
     */
    private $resumer;

    /**
     * @var RunJournal
     */
    private $journal;

    /**
     * Seconds an answered step is left to its own resume before it is retried.
     */
    public const RETRY_AFTER = 60;

    /**
     * @param ActorSteps         $steps
     * @param WaitingStepResumer $resumer
     * @param RunJournal|null    $journal
     */
    public function __construct(ActorSteps $steps, WaitingStepResumer $resumer, RunJournal $journal = null)
    {
        $this->steps = $steps;
        $this->resumer = $resumer;
        $this->journal = $journal ?: new RunJournal();
    }

    /**
     * Hands the answers that never reached the engine to it again.
     *
     * @param  \DateTimeInterface $at
     * @return int The runs taken on.
     */
    public function retry(\DateTimeInterface $at)
    {
        $before = gmdate('Y-m-d H:i:s', $at->getTimestamp() - self::RETRY_AFTER);
        $retried = 0;
        foreach ($this->steps->answered($this->journal->waitingActorStepIds(), $before) as $step) {
            $outputs = $step['kind'] === RunAgentActor::KIND
                ? ['outcome' => $step['outcome'], 'comment' => $step['comment']]
                : ['reply' => $step['comment']];
            $this->resumer->resume($step['id'], (string) $step['outcome'], $outputs);
            $retried++;
        }

        return $retried;
    }

    /**
     * @param  \DateTimeInterface $at
     * @return int The steps timed out.
     */
    public function run(\DateTimeInterface $at)
    {
        $now = gmdate('Y-m-d H:i:s', $at->getTimestamp());
        $timedOut = 0;
        foreach ($this->steps->overdue($now) as $step) {
            $comment = $step['status'] === ActorStepStatus::PENDING
                ? "The agent's runtime did not claim the step by {$step['claimBy']} UTC."
                : "The agent did not report by {$step['finishBy']} UTC.";
            $won = $this->steps->transition(
                $step['id'],
                [ActorStepStatus::PENDING, ActorStepStatus::CLAIMED],
                [
                    'status' => ActorStepStatus::TIMED_OUT,
                    'outcome' => RunAgentActor::TIMEOUT_OUTCOME,
                    'comment' => $comment,
                    'finishedAt' => $now,
                ]
            );
            if (!$won) {
                // Reported or timed out by someone else in the meantime.
                continue;
            }
            $this->resumer->resume($step['id'], RunAgentActor::TIMEOUT_OUTCOME, [
                'outcome' => RunAgentActor::TIMEOUT_OUTCOME,
                'comment' => $comment,
                'timedOut' => true,
            ]);
            $timedOut++;
        }

        return $timedOut;
    }
}
