<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The `set_field` action: sets one field of the record the event is about.
 * Identity, project, placement and bookkeeping fields cannot be set, nor
 * the fields the assign action and, on issues, set_status own.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SetFieldAction extends WorkflowAction
{
    /**
     * Fields a workflow may not set.
     */
    public const PROTECTED_FIELDS = [
        'id', 'deleted', 'projectId', 'createdUser', 'createdUserName', 'dateCreated',
        'modifiedUser', 'modifiedUserName', 'dateModified', 'lastActivityDate',
        // Owned by assign, which validates the value.
        'assignee',
        // Move the record to another parent or project.
        'relatedTo', 'relatedId', 'issueId', 'projectShortcode',
    ];

    /**
     * Fields a workflow may not set on an issue, because set_status owns
     * them and checks the status belongs to the issue's project.
     */
    public const ISSUE_STATUS_FIELDS = ['status', 'statusId'];

    /**
     * {@inheritdoc}
     */
    public function run(array $config, RunContext $context)
    {
        $field = self::text($this->setting($config, 'field', $context), 'field');
        $record = $this->subject($context);
        $model = $this->describe($record)['entityType'];
        if (!in_array($model, (new TriggerEventCatalog())->models(), true)) {
            throw new WorkflowStepException("A workflow cannot change a {$model}.");
        }
        $protected = $model === 'Issue'
            ? array_merge(self::PROTECTED_FIELDS, self::ISSUE_STATUS_FIELDS)
            : self::PROTECTED_FIELDS;
        if (in_array($field, $protected, true)) {
            throw new WorkflowStepException("A workflow cannot set {$model}.{$field}.");
        }
        if (!array_key_exists($field, $this->fields($record))) {
            throw new WorkflowStepException("A {$model} has no field {$field}.");
        }

        return $this->updateSubject($record, [$field => $this->setting($config, 'value', $context)], $context);
    }
}
