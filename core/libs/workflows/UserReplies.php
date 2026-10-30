<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Answers the 'Ask a user' steps waiting on a record: a comment there by
 * the user who was asked takes the 'replied' output, with the reply as
 * {step.reply}; reassigning the record takes the 'reassigned' output, with
 * the new assignee as {step.assigneeId}.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class UserReplies
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
     * @param ActorSteps         $steps
     * @param WaitingStepResumer $resumer
     */
    public function __construct(ActorSteps $steps, WaitingStepResumer $resumer)
    {
        $this->steps = $steps;
        $this->resumer = $resumer;
    }

    /**
     * A comment was added to a record.
     *
     * @param  string $relatedTo The commented record's type, e.g. issue.
     * @param  string $relatedId
     * @param  string $userId    Who wrote it.
     * @param  string $commentId
     * @param  string $text
     * @return int The steps answered.
     */
    public function commented($relatedTo, $relatedId, $userId, $commentId, $text)
    {
        $answered = 0;
        foreach ($this->steps->waitingOn($relatedTo, $relatedId) as $step) {
            if ((string) $step['actorUserId'] !== (string) $userId) {
                continue;
            }
            $answered += $this->answer($step, AskUserActor::REPLIED, [
                'userId' => (string) $userId,
                'replyId' => (string) $commentId,
                'reply' => (string) $text,
            ]);
        }

        return $answered;
    }

    /**
     * A record's assignee changed.
     *
     * @param  string $entityType e.g. issue.
     * @param  string $entityId
     * @param  string $assigneeId The new assignee.
     * @return int The steps answered.
     */
    public function reassigned($entityType, $entityId, $assigneeId)
    {
        $answered = 0;
        foreach ($this->steps->waitingOn($entityType, $entityId) as $step) {
            $answered += $this->answer($step, AskUserActor::REASSIGNED, ['assigneeId' => (string) $assigneeId]);
        }

        return $answered;
    }

    /**
     * @param  array  $step
     * @param  string $port
     * @param  array  $outputs
     * @return int 1 when this call answered the step, 0 when another did.
     */
    private function answer(array $step, $port, array $outputs)
    {
        $won = $this->steps->transition($step['id'], [ActorStepStatus::PENDING], [
            'status' => ActorStepStatus::FINISHED,
            'outcome' => $port,
            'comment' => isset($outputs['reply']) ? $outputs['reply'] : null,
            'finishedAt' => RunJournal::now(),
        ]);
        if (!$won) {
            return 0;
        }
        $this->resumer->resume($step['id'], $port, $outputs);

        return 1;
    }
}
