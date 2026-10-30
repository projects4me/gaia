<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The `set_field` action: sets one field of the record the event is about.
 * Identity, project and bookkeeping fields cannot be set.
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
    ];

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
        if (in_array($field, self::PROTECTED_FIELDS, true)) {
            throw new WorkflowStepException("A workflow cannot set {$model}.{$field}.");
        }
        if (!array_key_exists($field, $this->fields($record))) {
            throw new WorkflowStepException("A {$model} has no field {$field}.");
        }

        return $this->updateSubject($record, [$field => $this->setting($config, 'value', $context)], $context);
    }
}
