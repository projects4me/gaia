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
