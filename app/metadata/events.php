<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * The event registry: every domain event Gaia raises, keyed by name, and the
 * channels it goes out on (live-updates D-013). EventRegistry reads it once
 * per request. Nothing reaches Hermes unless its entry is enabled and lists
 * the `hermes` channel.
 *
 * Each entry:
 * - description: what happened.
 * - scopes:      the scopes it goes to, one or more of `project` (projectId is
 *                the project), `user` (projectId is user:<id>), `runtimes`
 *                (the agent runtimes' scope) and `service` (service:<kind>).
 * - serviceKind: with the `service` scope only, and then required: the kind
 *                of service, e.g. `ares` for service:ares.
 * - source:      optional, the write that raises it, in TriggerEventCatalog's
 *                shape: model, on (create|update), label, fields and optionally
 *                to (field => the value it must change to).
 * - channels:    `hermes` (live event) and/or `workflow` (workflow trigger).
 * - consumers:   who listens, for documentation only.
 * - enabled:     false stops the event on every channel.
 * - planned:     optional, true when Hermes may already allow the event but
 *                Gaia does not raise it yet: no component may emit it
 *                (gaia-dev check live-events), it is not published on its
 *                channels, and enabled() still reports true. Drop it when
 *                Gaia publishes it.
 *
 * Hermes keeps its own allowlist (hermes/src/contract/names.js): a name on
 * the hermes channel must also be there, and every allowlisted name must be
 * here on the hermes channel, planned included.
 */

return [
    'issue.created' => [
        'description' => 'Issue created.',
        'scopes' => ['project'],
        'source' => [
            'model' => 'Issue', 'on' => 'create', 'label' => 'Issue created', 'fields' => [],
        ],
        'channels' => ['hermes', 'workflow'],
        'consumers' => ['prometheus', 'workflows'],
        'enabled' => true,
    ],
    'issue.modified' => [
        'description' => 'Issue changed in any field but bookkeeping.',
        'scopes' => ['project'],
        'source' => [
            'model' => 'Issue', 'on' => 'update', 'label' => 'Issue modified', 'fields' => [],
        ],
        'channels' => ['workflow'],
        'consumers' => ['workflows'],
        'enabled' => true,
    ],
    'issue.status.changed' => [
        'description' => 'Issue status or milestone lane changed.',
        'scopes' => ['project'],
        'source' => [
            'model' => 'Issue', 'on' => 'update', 'label' => 'Issue status changed',
            'fields' => ['status', 'statusId'],
        ],
        'channels' => ['hermes', 'workflow'],
        'consumers' => ['prometheus', 'workflows'],
        'enabled' => true,
    ],
    'issue.assignee.changed' => [
        'description' => 'Issue assignee changed.',
        'scopes' => ['project'],
        'source' => [
            'model' => 'Issue', 'on' => 'update', 'label' => 'Issue assignee changed',
            'fields' => ['assignee'],
        ],
        'channels' => ['hermes', 'workflow'],
        'consumers' => ['prometheus', 'workflows'],
        'enabled' => true,
    ],
    'issue.dates.changed' => [
        'description' => 'Issue start or end date changed.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'issue.dependency.created' => [
        'description' => 'Issue parent/dependency created.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'issue.dependency.deleted' => [
        'description' => 'Issue parent/dependency removed.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'milestone.created' => [
        'description' => 'Milestone created.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'milestone.completed' => [
        'description' => 'Milestone status transitioned to completed.',
        'scopes' => ['project'],
        'source' => [
            'model' => 'Milestone', 'on' => 'update', 'label' => 'Milestone completed',
            'fields' => ['status'], 'to' => ['status' => 'completed'],
        ],
        'channels' => ['hermes', 'workflow'],
        'consumers' => ['prometheus', 'workflows'],
        'enabled' => true,
    ],
    'comment.added' => [
        'description' => 'Comment added (any comment, not only conversation rooms).',
        'scopes' => ['project'],
        'source' => [
            'model' => 'Comment', 'on' => 'create', 'label' => 'Comment added', 'fields' => [],
        ],
        'channels' => ['workflow'],
        'consumers' => ['workflows'],
        'enabled' => true,
    ],
    'conversation.created' => [
        'description' => 'Conversation room created.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'conversation.comment.created' => [
        'description' => 'Conversation-room comment created.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'conversation.comment.updated' => [
        'description' => 'Conversation-room comment updated.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'conversation.comment.deleted' => [
        'description' => 'Conversation-room comment deleted.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'conversation.vote.added' => [
        'description' => 'Conversation-room vote added.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'conversation.vote.removed' => [
        'description' => 'Conversation-room vote removed.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'wiki.created' => [
        'description' => 'Wiki page created.',
        'scopes' => ['project'],
        'source' => [
            'model' => 'Wiki', 'on' => 'create', 'label' => 'Wiki page created', 'fields' => [],
        ],
        'channels' => ['workflow'],
        'consumers' => ['workflows'],
        'enabled' => true,
    ],
    'wiki.modified' => [
        'description' => 'Wiki page changed in any field but bookkeeping.',
        'scopes' => ['project'],
        'source' => [
            'model' => 'Wiki', 'on' => 'update', 'label' => 'Wiki page modified', 'fields' => [],
        ],
        'channels' => ['workflow'],
        'consumers' => ['workflows'],
        'enabled' => true,
    ],
    'time.logged' => [
        'description' => 'Time logged.',
        'scopes' => ['project'],
        'source' => [
            'model' => 'Timelog', 'on' => 'create', 'label' => 'Time logged', 'fields' => [],
        ],
        'channels' => ['workflow'],
        'consumers' => ['workflows'],
        'enabled' => true,
    ],
    'idea.created' => [
        'description' => 'Idea created.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'idea.updated' => [
        'description' => 'Idea title, description or status changed.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'idea.message.created' => [
        'description' => 'Idea message created.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'idea.plan.created' => [
        'description' => 'Idea plan created.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'idea.plan.status.changed' => [
        'description' => 'Idea plan status or error changed.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'idea.planissue.created' => [
        'description' => 'Idea plan issue created.',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'notification.created' => [
        'description' => 'System notification created for a recipient; scoped to user:<recipientId>.',
        'scopes' => ['user'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus'],
        'enabled' => true,
    ],
    'workflow.agent.run.requested' => [
        'description' => "A workflow's 'Run agent' step waits for the agent's runtime; scoped to "
            . 'user:<agentUserId>, and also runtimes for a delegable agent; ids only (D-010, D-012).',
        'scopes' => ['user', 'runtimes'],
        'channels' => ['hermes'],
        'consumers' => ['talos'],
        'enabled' => true,
    ],
    'workflow.run.finished' => [
        'description' => "A workflow run completed or failed; scoped to the run's project; ids only, no error text.",
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus', 'talos'],
        'enabled' => true,
    ],
    'user.access.changed' => [
        'description' => "A user's access changed: account status, session revocation, a membership "
            . 'or a role assignment created, changed or deleted; scoped to user:<userId>, user id only (D-011).',
        'scopes' => ['user'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus', 'hermes'],
        'enabled' => true,
    ],
    'user.mentioned' => [
        'description' => 'A user was mentioned in a record; scoped to user:<mentionedUserId>; ids only.',
        'scopes' => ['user'],
        'channels' => ['hermes'],
        'consumers' => ['talos', 'prometheus'],
        'enabled' => true,
    ],
    'doc.created' => [
        'description' => 'Doc created; planned, Gaia does not publish it yet (agaia-4531).',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus', 'collab'],
        'enabled' => true,
        'planned' => true,
    ],
    'doc.updated' => [
        'description' => 'Doc updated; planned, Gaia does not publish it yet (agaia-4531).',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus', 'collab'],
        'enabled' => true,
        'planned' => true,
    ],
    'doc.deleted' => [
        'description' => 'Doc deleted; planned, Gaia does not publish it yet (agaia-4531).',
        'scopes' => ['project'],
        'channels' => ['hermes'],
        'consumers' => ['prometheus', 'collab'],
        'enabled' => true,
        'planned' => true,
    ],
    'search.semantic.changed' => [
        'description' => 'A record\'s semantic search text changed; scoped to service:ares; planned, Gaia '
            . 'does not publish it yet (agaia-4658).',
        'scopes' => ['service'],
        'serviceKind' => 'ares',
        'channels' => ['hermes'],
        'consumers' => ['ares'],
        'enabled' => true,
        'planned' => true,
    ],
];
