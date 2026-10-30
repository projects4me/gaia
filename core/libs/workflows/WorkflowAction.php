<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * An action node's work. Subclasses carry out one action kind; this base
 * class resolves their settings and finds the record the event is about.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
abstract class WorkflowAction
{
    /**
     * @var WorkflowRecords
     */
    protected $records;

    /**
     * @var UserSelector
     */
    protected $users;

    /**
     * @var ExpressionResolver
     */
    protected $expressions;

    /**
     * @param WorkflowRecords    $records
     * @param UserSelector       $users
     * @param ExpressionResolver $expressions
     */
    public function __construct(WorkflowRecords $records, UserSelector $users, ExpressionResolver $expressions)
    {
        $this->records = $records;
        $this->users = $users;
        $this->expressions = $expressions;
    }

    /**
     * Carries out the action.
     *
     * @param  array      $config The node's settings, unresolved.
     * @param  RunContext $context
     * @return array The step's outputs.
     * @throws WorkflowStepException
     */
    abstract public function run(array $config, RunContext $context);

    /**
     * A setting with its expressions resolved, or the default when unset.
     *
     * @param  array      $config
     * @param  string     $name
     * @param  RunContext $context
     * @param  mixed      $default
     * @return mixed
     */
    protected function setting(array $config, $name, RunContext $context, $default = null)
    {
        if (!isset($config[$name]) || $config[$name] === '') {
            return $default;
        }

        return $this->expressions->resolve($config[$name], $context->values());
    }

    /**
     * The project member a user setting names; the workflow's acting user
     * when the setting is unset and a default is allowed. Either way the
     * user must be a member of the run's project.
     *
     * @param  array      $config
     * @param  string     $name
     * @param  RunContext $context
     * @param  bool       $defaultsToActingUser
     * @return string
     * @throws WorkflowStepException
     */
    protected function user(array $config, $name, RunContext $context, $defaultsToActingUser = false)
    {
        $setting = isset($config[$name]) ? $config[$name] : null;
        if (($setting === null || $setting === '') && $defaultsToActingUser) {
            // The acting user must be a project member like a chosen one.
            $setting = $context->actingUserId;
        }

        return $this->users->select($name, $setting, $context->values(), $this->expressions);
    }

    /**
     * The record the event is about.
     *
     * @param  RunContext $context
     * @return \Phalcon\Mvc\ModelInterface
     * @throws WorkflowStepException When the event has none or it is gone.
     */
    protected function subject(RunContext $context)
    {
        $subject = $context->subject();
        if ($subject === null) {
            throw new WorkflowStepException('The event is not about a record.');
        }

        list($model, $id) = $subject;
        $record = $this->records->find($model, $id);
        if ($record === null) {
            throw new WorkflowStepException("{$model} {$id} no longer exists.");
        }

        return $record;
    }

    /**
     * Changes the subject's fields as the acting user and reports the change.
     *
     * @param  \Phalcon\Mvc\ModelInterface $record
     * @param  array                       $values
     * @param  RunContext                  $context
     * @return array The step's outputs.
     */
    protected function updateSubject($record, array $values, RunContext $context)
    {
        $this->records->update($record, $values, $context->actingUserId);
        $context->changed($record, TriggerEventCatalog::ON_UPDATE);

        return $this->describe($record) + ['changed' => $values];
    }

    /**
     * Creates a record as the user and reports it.
     *
     * @param  string     $model
     * @param  array      $values
     * @param  string     $userId
     * @param  RunContext $context
     * @return array The step's outputs.
     */
    protected function createRecord($model, array $values, $userId, RunContext $context)
    {
        $record = $this->records->create($model, $values, $userId);
        $context->changed($record, TriggerEventCatalog::ON_CREATE);

        return $this->describe($record) + ['createdUser' => $userId];
    }

    /**
     * The record's fields, name => editor type.
     *
     * @param  \Phalcon\Mvc\ModelInterface $record
     * @return array
     */
    protected function fields($record)
    {
        return $this->records->fields($this->describe($record)['entityType']);
    }

    /**
     * The record's model name and id, as step outputs.
     *
     * @param  \Phalcon\Mvc\ModelInterface $record
     * @return array
     */
    protected function describe($record)
    {
        return [
            'entityType' => $this->records->modelName($record),
            'entityId' => (string) $record->readAttribute('id'),
        ];
    }

    /**
     * @param  mixed  $value
     * @param  string $name
     * @return string
     * @throws WorkflowStepException When the value is empty.
     */
    protected static function text($value, $name)
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            throw new WorkflowStepException("config.{$name} resolves to nothing.");
        }

        return (string) $value;
    }
}
