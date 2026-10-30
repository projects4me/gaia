<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * What an agent's runtime may do to its 'Run agent' step: claim it
 * (`status: claimed`), then report its outcome with a comment
 * (`outcome: done|needs_input|blocked|failed|limit`, `comment`). A report
 * finishes the step and the workflow goes on by the outcome's port, with
 * the outcome and comment readable as {step.outcome} and {step.comment}.
 *
 * Only the step's own agent may do either, and each happens once.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActorStepReports
{
    /**
     * The fields an update may carry besides the id.
     */
    private const FIELDS = ['id', 'status', 'outcome', 'comment'];

    /**
     * @var ActorSteps
     */
    private $steps;

    /**
     * @var WaitingStepResumer
     */
    private $resumer;

    /**
     * @param ActorSteps         $steps
     * @param WaitingStepResumer $resumer
     */
    public function __construct(ActorSteps $steps, WaitingStepResumer $resumer)
    {
        $this->steps = $steps;
        $this->resumer = $resumer;
    }

    /**
     * Claims the step or reports its outcome, as the update asks.
     *
     * @param  string $stepId
     * @param  string $userId  The user making the update.
     * @param  array  $update  status: claimed; or outcome and comment.
     * @return void
     * @throws ActorStepRefusal
     */
    public function update($stepId, $userId, array $update)
    {
        $unknown = array_diff(array_keys($update), self::FIELDS);
        if ($unknown) {
            throw new ActorStepRefusal(
                'Only status, outcome and comment can be updated: not ' . implode(', ', $unknown) . '.',
                422
            );
        }

        $step = $this->steps->find($stepId);
        if ($step === null) {
            throw new ActorStepRefusal("Actor step {$stepId} does not exist.", 404);
        }
        if ((string) $step['actorUserId'] !== (string) $userId) {
            throw new ActorStepRefusal('Only the step\'s own agent can claim it or report on it.', 403);
        }
        if ($step['kind'] !== RunAgentActor::KIND) {
            throw new ActorStepRefusal('Only Run agent steps are claimed and reported here.', 422);
        }

        if (isset($update['outcome'])) {
            $this->report($step, $update);
            return;
        }
        if (isset($update['status']) && $update['status'] === ActorStepStatus::CLAIMED) {
            $this->claim($step);
            return;
        }

        throw new ActorStepRefusal('Send status: claimed to claim the step, or an outcome to report it.', 422);
    }

    /**
     * @param  array $step
     * @return void
     * @throws ActorStepRefusal When it is claimed already or over.
     */
    private function claim(array $step)
    {
        $claimed = $this->steps->transition($step['id'], [ActorStepStatus::PENDING], [
            'status' => ActorStepStatus::CLAIMED,
            'claimedAt' => RunJournal::now(),
        ]);
        if (!$claimed) {
            throw new ActorStepRefusal("Actor step {$step['id']} is not pending.", 409);
        }
    }

    /**
     * @param  array $step
     * @param  array $update
     * @return void
     * @throws ActorStepRefusal When the outcome is unknown or the step is not claimed.
     */
    private function report(array $step, array $update)
    {
        $outcome = $update['outcome'];
        if (!in_array($outcome, RunAgentActor::OUTCOMES, true)) {
            throw new ActorStepRefusal('outcome must be one of ' . implode(', ', RunAgentActor::OUTCOMES) . '.', 422);
        }
        if (isset($update['status']) && $update['status'] !== ActorStepStatus::FINISHED) {
            throw new ActorStepRefusal('A report sets status finished, or leaves it out.', 422);
        }
        $comment = isset($update['comment']) && is_scalar($update['comment']) ? (string) $update['comment'] : null;

        $reported = $this->steps->transition($step['id'], [ActorStepStatus::CLAIMED], [
            'status' => ActorStepStatus::FINISHED,
            'outcome' => $outcome,
            'comment' => $comment,
            'finishedAt' => RunJournal::now(),
        ]);
        if (!$reported) {
            throw new ActorStepRefusal("Actor step {$step['id']} is not claimed, so it cannot be reported.", 409);
        }

        $this->resumer->resume($step['id'], $outcome, ['outcome' => $outcome, 'comment' => $comment]);
    }
}
