<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;

/**
 * The domain events a workflow can start on, per module: which write of
 * which model raises each one and, for update events, which changed fields
 * it watches and, for a transition, the values they must change to. Events
 * Hermes also publishes keep their names from EventNames, so a live update
 * and a workflow trigger mean the same thing.
 *
 * The `schedule` trigger is not a domain event: a task raises it from the
 * trigger's cron expression (see ScheduleTriggerRunner).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TriggerEventCatalog
{
    public const ON_CREATE = 'create';
    public const ON_UPDATE = 'update';

    /**
     * The trigger kind a task raises on a cron schedule.
     */
    public const SCHEDULE = 'schedule';

    public const ISSUE_MODIFIED = 'issue.modified';
    public const COMMENT_ADDED = 'comment.added';
    public const WIKI_CREATED = 'wiki.created';
    public const WIKI_MODIFIED = 'wiki.modified';
    public const TIME_LOGGED = 'time.logged';

    /**
     * The milestone status that completes it.
     */
    public const MILESTONE_COMPLETED_STATUS = 'completed';

    /**
     * Fields every save touches; a change to only these is not a modification.
     *
     * @var string[]
     */
    public const BOOKKEEPING_FIELDS = [
        'dateModified', 'modifiedUser', 'modifiedUserName', 'lastActivityDate',
    ];

    /**
     * Events by name: the model, the write that raises it, a label and, for
     * update events, the watched fields (none: any change but bookkeeping)
     * and optionally a transition, `to`: field => the value it must change
     * to from another value.
     *
     * @var array
     */
    private const EVENTS = [
        EventNames::ISSUE_CREATED => [
            'model' => 'Issue', 'on' => self::ON_CREATE, 'label' => 'Issue created', 'fields' => [],
        ],
        self::ISSUE_MODIFIED => [
            'model' => 'Issue', 'on' => self::ON_UPDATE, 'label' => 'Issue modified', 'fields' => [],
        ],
        EventNames::ISSUE_ASSIGNEE_CHANGED => [
            'model' => 'Issue', 'on' => self::ON_UPDATE, 'label' => 'Issue assignee changed',
            'fields' => ['assignee'],
        ],
        EventNames::ISSUE_STATUS_CHANGED => [
            'model' => 'Issue', 'on' => self::ON_UPDATE, 'label' => 'Issue status changed',
            'fields' => ['status', 'statusId'],
        ],
        self::COMMENT_ADDED => [
            'model' => 'Comment', 'on' => self::ON_CREATE, 'label' => 'Comment added', 'fields' => [],
        ],
        self::WIKI_CREATED => [
            'model' => 'Wiki', 'on' => self::ON_CREATE, 'label' => 'Wiki page created', 'fields' => [],
        ],
        self::WIKI_MODIFIED => [
            'model' => 'Wiki', 'on' => self::ON_UPDATE, 'label' => 'Wiki page modified', 'fields' => [],
        ],
        self::TIME_LOGGED => [
            'model' => 'Timelog', 'on' => self::ON_CREATE, 'label' => 'Time logged', 'fields' => [],
        ],
        EventNames::MILESTONE_COMPLETED => [
            'model' => 'Milestone', 'on' => self::ON_UPDATE, 'label' => 'Milestone completed',
            'fields' => ['status'], 'to' => ['status' => self::MILESTONE_COMPLETED_STATUS],
        ],
    ];

    /**
     * Every event name, in catalog order.
     *
     * @return string[]
     */
    public function names()
    {
        return array_keys(self::EVENTS);
    }

    /**
     * @param  string $name
     * @return bool
     */
    public function has($name)
    {
        return isset(self::EVENTS[$name]);
    }

    /**
     * The event's definition: model, on, label, watched fields and, for a
     * transition, `to`.
     *
     * @param  string $name
     * @return array
     */
    public function definition($name)
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Unknown workflow trigger event {$name}.");
        }

        return self::EVENTS[$name];
    }

    /**
     * The models that raise trigger events.
     *
     * @return string[]
     */
    public function models()
    {
        return array_values(array_unique(array_column(self::EVENTS, 'model')));
    }

    /**
     * The events a write of the model raises. An update raises an event when
     * one of its watched fields changed or, with none watched, when anything
     * but bookkeeping changed. An event with a transition is raised only
     * when each of its fields changed to the named value from another value.
     *
     * @param  string $model Model name, e.g. `Issue`.
     * @param  string $on    ON_CREATE or ON_UPDATE.
     * @param  array  $audit The update's change: field => ['old' => ..., 'new' => ...].
     * @return string[]
     */
    public function raisedBy($model, $on, array $audit = [])
    {
        $changed = array_values(array_diff(array_keys($audit), self::BOOKKEEPING_FIELDS));
        $names = [];
        foreach (self::EVENTS as $name => $event) {
            if ($event['model'] !== $model || $event['on'] !== $on) {
                continue;
            }
            if ($on === self::ON_CREATE) {
                $names[] = $name;
                continue;
            }
            $watched = $event['fields'] ? array_intersect($event['fields'], $changed) : $changed;
            if ($watched && $this->transitioned($event, $audit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Whether the update moved each of the event's transition fields to its
     * value from another value; true for an event without a transition.
     *
     * @param  array $event
     * @param  array $audit field => ['old' => ..., 'new' => ...]
     * @return bool
     */
    private function transitioned(array $event, array $audit)
    {
        $to = isset($event['to']) ? $event['to'] : [];
        foreach ($to as $field => $value) {
            $change = isset($audit[$field]) && is_array($audit[$field]) ? $audit[$field] : [];
            $new = array_key_exists('new', $change) ? $change['new'] : null;
            $old = array_key_exists('old', $change) ? $change['old'] : null;
            if ((string) $new !== (string) $value || (string) $old === (string) $value) {
                return false;
            }
        }

        return true;
    }
}
