<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use Gaia\MVC\Models\Issuestatus;
use Gaia\MVC\Models\Project;
use Gaia\MVC\Models\User;

/**
 * Reads and writes the records workflow actions work on. A write is made
 * as a given user, so the record's createdUser / modifiedUser (set by the
 * model behaviours from the current user) is that user and not whoever
 * caused the event.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowRecords
{
    /**
     * A live record of a model, by id.
     *
     * @param  string $model The model name, such as Issue.
     * @param  string $id
     * @return \Phalcon\Mvc\ModelInterface|null
     */
    public function find($model, $id)
    {
        $class = self::modelClass($model);
        // `deleted` is a boolean on some tables and a smallint on others (issues), so it is
        // checked here rather than compared in the query.
        $record = $class::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => (string) $id],
        ]);

        return $record && !$record->readAttribute('deleted') ? $record : null;
    }

    /**
     * Creates a record as the user.
     *
     * @param  string $model
     * @param  array  $values
     * @param  string $userId
     * @return \Phalcon\Mvc\ModelInterface
     * @throws WorkflowStepException When the record does not validate.
     */
    public function create($model, array $values, $userId)
    {
        $class = self::modelClass($model);
        $record = new $class();
        foreach ($values as $field => $value) {
            $record->writeAttribute($field, $value);
        }

        return $this->saveAs($record, $userId, "create the {$model}");
    }

    /**
     * Changes fields of a record as the user. Afterwards `$record->audit`
     * holds what changed.
     *
     * @param  \Phalcon\Mvc\ModelInterface $record
     * @param  array                       $values
     * @param  string                      $userId
     * @return \Phalcon\Mvc\ModelInterface
     * @throws WorkflowStepException When the record does not validate.
     */
    public function update($record, array $values, $userId)
    {
        foreach ($values as $field => $value) {
            $record->writeAttribute($field, $value);
        }

        return $this->saveAs($record, $userId, 'update the ' . $this->modelName($record));
    }

    /**
     * The model name of a record, such as Issue.
     *
     * @param  \Phalcon\Mvc\ModelInterface $record
     * @return string
     */
    public function modelName($record)
    {
        return (new \ReflectionClass($record))->getShortName();
    }

    /**
     * A model's fields, name => editor type (see ModelFieldTypes).
     *
     * @param  string $model
     * @return array
     */
    public function fields($model)
    {
        return (new ModelFieldTypes())->forModel($model);
    }

    /**
     * The project's issue status with this id or name.
     *
     * @param  string $projectId
     * @param  string $status
     * @return array|null {id, name}
     */
    public function issueStatus($projectId, $status)
    {
        $row = Issuestatus::findFirst([
            'conditions' => 'projectId = :projectId: AND (id = :status: OR name = :status:) AND deleted = false',
            'bind' => ['projectId' => (string) $projectId, 'status' => (string) $status],
        ]);

        return $row ? ['id' => (string) $row->id, 'name' => (string) $row->name] : null;
    }

    /**
     * The short code of a live project.
     *
     * @param  string $projectId
     * @return string|null
     */
    public function projectShortCode($projectId)
    {
        // projects.deleted is a smallint column (see LiveRecords).
        $project = Project::findFirst([
            'conditions' => 'id = :id: AND deleted = 0',
            'bind' => ['id' => (string) $projectId],
        ]);

        return $project ? (string) $project->shortCode : null;
    }

    /**
     * The model class of a model name or record key (`issue` → Issue).
     *
     * @param  string $model
     * @return string
     * @throws WorkflowStepException When there is no such model.
     */
    public static function modelClass($model)
    {
        $class = '\\Gaia\\MVC\\Models\\' . ucfirst(strtolower((string) $model));
        if (!preg_match('/^[a-z]+$/i', (string) $model) || !class_exists($class)) {
            throw new WorkflowStepException("There is no {$model} model.");
        }

        return $class;
    }

    /**
     * @param  \Phalcon\Mvc\ModelInterface $record
     * @param  string                      $userId
     * @param  string                      $what
     * @return \Phalcon\Mvc\ModelInterface
     * @throws WorkflowStepException
     */
    private function saveAs($record, $userId, $what)
    {
        global $currentUser;

        $user = User::findFirst(['conditions' => 'id = :id:', 'bind' => ['id' => (string) $userId]]);
        if (!$user) {
            throw new WorkflowStepException("User {$userId} does not exist.");
        }

        $previous = $currentUser;
        $currentUser = $user;
        try {
            $saved = $record->save();
        } finally {
            $currentUser = $previous;
        }

        if (!$saved) {
            $messages = array_map(function ($message) {
                return $message->getMessage();
            }, $record->getMessages());
            throw new WorkflowStepException("Could not {$what}: " . implode(' ', $messages));
        }

        return $record;
    }
}
