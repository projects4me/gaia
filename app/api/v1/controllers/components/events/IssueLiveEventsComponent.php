<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events;

use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Phalcon\Events\Event;

/**
 * This component publishes issue create, status, assignee, date, and
 * dependency events after persist.
 *
 * @class   IssueLiveEventsComponent
 * @package Gaia\MVC\REST\Controllers\Components\Events
 */
class IssueLiveEventsComponent extends LiveEventsComponent
{
    /**
     * Issue fields included on issue.created.
     *
     * @var  array
     * @type array
     */
    const CREATED_FIELDS = array(
        'subject',
        'status',
        'statusId',
        'assignee',
        'milestoneId',
        'startDate',
        'endDate',
        'parentId',
        'issueNumber',
        'projectId',
        'priority',
        'type',
    );

    /**
     * Publishes issue.created after a successful persist.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted issue.
     * @method afterCreate
     * @return void
     */
    public function afterCreate(Event $event, $controller, $model)
    {
        $id = $this->modelId($model);
        if ($id === null) {
            return;
        }

        $this->publish(
            EventNames::ISSUE_CREATED,
            $model,
            'issue',
            $id,
            $this->pick(self::CREATED_FIELDS, $model),
            $this->issueMeta($model)
        );
    }

    /**
     * Publishes selective update events from the issue audit diff.
     *
     * @param  Event $event      The event object.
     * @param  mixed $controller The controller that fired the event.
     * @param  mixed $model      The persisted issue.
     * @method afterUpdate
     * @return void
     */
    public function afterUpdate(Event $event, $controller, $model)
    {
        $id = $this->modelId($model);
        if ($id === null || empty($model->audit) || !is_array($model->audit)) {
            return;
        }

        $this->publishStatusChanged($model, $id);
        $this->publishAssigneeChanged($model, $id);
        $this->publishDatesChanged($model, $id);
        $this->publishDependencyChanged($model, $id);
    }

    /**
     * Publishes issue.status.changed when status, statusId, or milestoneId changed.
     *
     * @param  mixed  $model The persisted issue.
     * @param  string $id    Issue id.
     * @method publishStatusChanged
     * @return void
     */
    protected function publishStatusChanged($model, $id)
    {
        $changes = $this->changesFromAudit($model, array('status', 'statusId', 'milestoneId'));
        if (!$changes) {
            return;
        }

        $this->publish(EventNames::ISSUE_STATUS_CHANGED, $model, 'issue', $id, $changes, $this->issueMeta($model));
    }

    /**
     * Publishes issue.assignee.changed when assignee changed.
     *
     * @param  mixed  $model The persisted issue.
     * @param  string $id    Issue id.
     * @method publishAssigneeChanged
     * @return void
     */
    protected function publishAssigneeChanged($model, $id)
    {
        $changes = $this->changesFromAudit($model, array('assignee'));
        if (!$changes) {
            return;
        }

        $this->publish(EventNames::ISSUE_ASSIGNEE_CHANGED, $model, 'issue', $id, $changes, $this->issueMeta($model));
    }

    /**
     * Publishes issue.dates.changed when startDate or endDate changed.
     *
     * @param  mixed  $model The persisted issue.
     * @param  string $id    Issue id.
     * @method publishDatesChanged
     * @return void
     */
    protected function publishDatesChanged($model, $id)
    {
        $changes = $this->changesFromAudit($model, array('startDate', 'endDate'));
        if (!$changes) {
            return;
        }

        $this->publish(EventNames::ISSUE_DATES_CHANGED, $model, 'issue', $id, $changes, $this->issueMeta($model));
    }

    /**
     * Maps parentId audit old/new onto dependency created and/or deleted events.
     *
     * @param  mixed  $model The persisted issue.
     * @param  string $id    Issue id.
     * @method publishDependencyChanged
     * @return void
     */
    protected function publishDependencyChanged($model, $id)
    {
        if (!array_key_exists('parentId', $model->audit)) {
            return;
        }

        $oldParent = $this->auditValue($model, 'parentId', 'old');
        $newParent = $this->auditValue($model, 'parentId', 'new');
        $oldBlank = $this->isBlank($oldParent);
        $newBlank = $this->isBlank($newParent);

        if ($oldBlank && !$newBlank) {
            $this->publishDependencyCreated($model, $id, $newParent);
            return;
        }

        if (!$oldBlank && $newBlank) {
            $this->publishDependencyDeleted($model, $id, $oldParent);
            return;
        }

        if (!$oldBlank && !$newBlank && (string) $oldParent !== (string) $newParent) {
            $this->publishDependencyDeleted($model, $id, $oldParent);
            $this->publishDependencyCreated($model, $id, $newParent);
        }
    }

    /**
     * Publishes issue.dependency.created for the new parent.
     *
     * @param  mixed  $model    The persisted issue.
     * @param  string $id       Issue id.
     * @param  mixed  $parentId New parent issue id.
     * @method publishDependencyCreated
     * @return void
     */
    protected function publishDependencyCreated($model, $id, $parentId)
    {
        $this->publish(
            EventNames::ISSUE_DEPENDENCY_CREATED,
            $model,
            'issue',
            $id,
            array(
                'parentId' => $parentId,
                'issueId' => $id,
            ),
            array_merge($this->issueMeta($model), array(
                'predecessorIssueId' => $parentId,
                'successorIssueId' => $id,
            ))
        );
    }

    /**
     * Publishes issue.dependency.deleted for the previous parent.
     *
     * @param  mixed  $model    The persisted issue.
     * @param  string $id       Issue id.
     * @param  mixed  $parentId Previous parent issue id.
     * @method publishDependencyDeleted
     * @return void
     */
    protected function publishDependencyDeleted($model, $id, $parentId)
    {
        $this->publish(
            EventNames::ISSUE_DEPENDENCY_DELETED,
            $model,
            'issue',
            $id,
            array(
                'parentId' => null,
                'issueId' => $id,
            ),
            array_merge($this->issueMeta($model), array(
                'predecessorIssueId' => $parentId,
                'successorIssueId' => $id,
            ))
        );
    }

    /**
     * Returns issueNumber metadata included on issue envelopes.
     *
     * @param  mixed $model The persisted issue.
     * @method issueMeta
     * @return array
     */
    protected function issueMeta($model)
    {
        return array(
            'issueNumber' => isset($model->issueNumber) ? $model->issueNumber : null,
        );
    }
}
