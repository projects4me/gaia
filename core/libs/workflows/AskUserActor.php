<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The `ask_user` actor: posts the question as a comment on the record the
 * event is about (on the commented record when the event is about a
 * comment), as the workflow's acting user, and records a pending step for
 * the project member `userId` names. The run waits until that user replies
 * with a comment there or the record is reassigned (see UserReplies).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AskUserActor extends WorkflowAction
{
    public const KIND = 'ask_user';

    /**
     * The port taken when the user replies with a comment.
     */
    public const REPLIED = 'replied';

    /**
     * The port taken when the record is reassigned before the user replies.
     */
    public const REASSIGNED = 'reassigned';

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
        list($relatedTo, $relatedId) = $this->commentTarget($this->subject($context));
        $userId = $this->user($config, 'userId', $context);
        $question = self::text($this->setting($config, 'question', $context), 'question');

        $comment = $this->createRecord('Comment', [
            'comment' => $question,
            'relatedTo' => $relatedTo,
            'relatedId' => $relatedId,
        ], $context->actingUserId, $context);

        $this->steps->create([
            'id' => $context->step['id'],
            'runId' => $context->runId,
            'workflowId' => (string) $context->workflow['id'],
            'nodeId' => $context->step['nodeId'],
            'kind' => self::KIND,
            'actorUserId' => $userId,
            'projectId' => $context->projectId(),
            'entityType' => $relatedTo,
            'entityId' => $relatedId,
            'prompt' => $question,
        ]);

        return ['userId' => $userId, 'questionId' => $comment['entityId']];
    }
}
