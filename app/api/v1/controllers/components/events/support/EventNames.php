<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events\Support;

/**
 * This class holds the allowlisted Hermes domain-event names. Keep in
 * lockstep with hermes/src/contract/names.js EVENT_ALLOWLIST.
 *
 * @class   EventNames
 * @package Gaia\MVC\REST\Controllers\Components\Events\Support
 */
class EventNames
{
    /**
     * Issue status or milestone lane changed.
     *
     * @var string
     */
    const ISSUE_STATUS_CHANGED = 'issue.status.changed';

    /**
     * Issue assignee changed.
     *
     * @var string
     */
    const ISSUE_ASSIGNEE_CHANGED = 'issue.assignee.changed';

    /**
     * Milestone created.
     *
     * @var string
     */
    const MILESTONE_CREATED = 'milestone.created';

    /**
     * Milestone status transitioned to completed.
     *
     * @var string
     */
    const MILESTONE_COMPLETED = 'milestone.completed';

    /**
     * Issue created.
     *
     * @var string
     */
    const ISSUE_CREATED = 'issue.created';

    /**
     * Issue start or end date changed.
     *
     * @var string
     */
    const ISSUE_DATES_CHANGED = 'issue.dates.changed';

    /**
     * Issue parent/dependency created.
     *
     * @var string
     */
    const ISSUE_DEPENDENCY_CREATED = 'issue.dependency.created';

    /**
     * Issue parent/dependency removed.
     *
     * @var string
     */
    const ISSUE_DEPENDENCY_DELETED = 'issue.dependency.deleted';

    /**
     * Conversation-room comment created.
     *
     * @var string
     */
    const CONVERSATION_COMMENT_CREATED = 'conversation.comment.created';

    /**
     * Conversation-room comment updated.
     *
     * @var string
     */
    const CONVERSATION_COMMENT_UPDATED = 'conversation.comment.updated';

    /**
     * Conversation-room comment deleted.
     *
     * @var string
     */
    const CONVERSATION_COMMENT_DELETED = 'conversation.comment.deleted';

    /**
     * Conversation-room vote added.
     *
     * @var string
     */
    const CONVERSATION_VOTE_ADDED = 'conversation.vote.added';

    /**
     * Conversation-room vote removed.
     *
     * @var string
     */
    const CONVERSATION_VOTE_REMOVED = 'conversation.vote.removed';

    /**
     * Conversation room created.
     *
     * @var string
     */
    const CONVERSATION_CREATED = 'conversation.created';

    /**
     * System notification created for a recipient.
     *
     * @var string
     */
    const NOTIFICATION_CREATED = 'notification.created';

    /**
     * Idea created.
     *
     * @var string
     */
    const IDEA_CREATED = 'idea.created';

    /**
     * Idea title, description or status changed.
     *
     * @var string
     */
    const IDEA_UPDATED = 'idea.updated';

    /**
     * Idea message created.
     *
     * @var string
     */
    const IDEA_MESSAGE_CREATED = 'idea.message.created';

    /**
     * Idea plan created.
     *
     * @var string
     */
    const IDEA_PLAN_CREATED = 'idea.plan.created';

    /**
     * Idea plan status or error changed.
     *
     * @var string
     */
    const IDEA_PLAN_STATUS_CHANGED = 'idea.plan.status.changed';

    /**
     * Idea plan issue created.
     *
     * @var string
     */
    const IDEA_PLANISSUE_CREATED = 'idea.planissue.created';

    /**
     * A workflow's 'Run agent' step waits for the agent's runtime. Scoped to
     * user:<agentUserId>; carries ids only (hermes D-017).
     *
     * @var string
     */
    const WORKFLOW_AGENT_RUN_REQUESTED = 'workflow.agent.run.requested';

    /**
     * Every name HermesPublisher is allowed to post.
     *
     * @var   array
     * @type  array
     */
    const ALL = array(
        self::ISSUE_STATUS_CHANGED,
        self::ISSUE_ASSIGNEE_CHANGED,
        self::MILESTONE_CREATED,
        self::MILESTONE_COMPLETED,
        self::ISSUE_CREATED,
        self::ISSUE_DATES_CHANGED,
        self::ISSUE_DEPENDENCY_CREATED,
        self::ISSUE_DEPENDENCY_DELETED,
        self::CONVERSATION_COMMENT_CREATED,
        self::CONVERSATION_COMMENT_UPDATED,
        self::CONVERSATION_COMMENT_DELETED,
        self::CONVERSATION_VOTE_ADDED,
        self::CONVERSATION_VOTE_REMOVED,
        self::CONVERSATION_CREATED,
        self::NOTIFICATION_CREATED,
        self::IDEA_CREATED,
        self::IDEA_UPDATED,
        self::IDEA_MESSAGE_CREATED,
        self::IDEA_PLAN_CREATED,
        self::IDEA_PLAN_STATUS_CHANGED,
        self::IDEA_PLANISSUE_CREATED,
        self::WORKFLOW_AGENT_RUN_REQUESTED,
    );

    /**
     * Returns true when the event name is in the Gaia/Hermes allowlist.
     *
     * @param  string $eventName The domain-event name to check.
     * @method isAllowed
     * @return bool
     */
    public static function isAllowed($eventName)
    {
        return in_array($eventName, self::ALL, true);
    }
}
