<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\MVC\Models\Doc;
use Gaia\MVC\Models\Doccontent;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior checks the content row of a doc: the docId must be a doc of the
 * same projectId, and a doc has one content row only (a deleted row counts, so
 * the row is not duplicated; the unique index on docId backs this up against
 * concurrent creates). The size of the state is kept in byteSize. Content of
 * a locked doc cannot be created, changed or deleted (Docs D-005).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class doccontentBehavior extends Behavior implements BehaviorInterface
{
    /**
     * Returning false from a before* event cancels the save.
     *
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            return $this->$eventType($model);
        }
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnCreate($model)
    {
        return $this->setByteSize($model) && $this->checkDoc($model) && $this->checkUnique($model)
            && $this->checkUnlocked($model, [$model->docId]);
    }

    /**
     * Runs before softDeleteBehavior (listed after this behavior), whose
     * update of the deleted flag does not cancel the delete when it fails.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeDelete($model)
    {
        return $this->checkUnlocked($model, [$model->docId]);
    }

    /**
     * Only a changed docId or projectId is checked again, so a later save
     * (e.g. a soft delete) does not fail because of data changed since.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        if (!$this->setByteSize($model)) {
            return false;
        }
        $snapshot = $model->getSnapshotData();
        if (!$this->checkUnlocked($model, [$snapshot['docId'] ?? null, $model->docId])) {
            return false;
        }
        if (is_array($snapshot)
            && (string) ($snapshot['docId'] ?? '') === (string) $model->docId
            && (string) ($snapshot['projectId'] ?? '') === (string) $model->projectId
        ) {
            return true;
        }

        return $this->checkDoc($model) && $this->checkUnique($model);
    }

    /**
     * The byteSize is the length of the stored state (the base64 text), whatever
     * the request sent. A state that is not a string is rejected.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function setByteSize($model)
    {
        if ($model->state !== null && !is_string($model->state)) {
            return $this->reject($model, 'state', 'state must be a string.');
        }
        $model->byteSize = strlen((string) $model->state);
        return true;
    }

    /**
     * Reject a docId that is not a doc of the content's project.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkDoc($model)
    {
        $doc = Doc::findFirst([
            'conditions' => 'id = :docId: AND projectId = :projectId: AND deleted = false',
            'bind' => [
                'docId' => (string) $model->docId,
                'projectId' => (string) $model->projectId,
            ],
        ]);
        if (!$doc) {
            return $this->reject($model, 'docId', 'docId must be a doc of the same project.');
        }

        return true;
    }

    /**
     * Reject a second content row for the same doc.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkUnique($model)
    {
        $modelClass = get_class($model);
        $count = $modelClass::count([
            'conditions' => 'docId = :docId: AND id <> :id:',
            'bind' => [
                'docId' => (string) $model->docId,
                'id' => (string) $model->id,
            ],
        ]);
        if ($count > 0) {
            return $this->reject($model, 'docId', 'The doc already has a content row.');
        }

        return true;
    }

    /**
     * Reject the save while any of the given docs is locked.
     *
     * @param  ModelInterface $model
     * @param  array          $docIds The stored and the new docId
     * @return bool
     */
    protected function checkUnlocked($model, array $docIds)
    {
        $docIds = array_values(array_unique(array_filter(array_map('strval', $docIds), 'strlen')));
        if (!empty($docIds) && $this->isAnyDocLocked($docIds)) {
            return $this->reject($model, 'docId', 'The doc is locked; its content cannot be changed.');
        }

        return true;
    }

    /**
     * Whether any of the docs is locked.
     *
     * @param  array $docIds
     * @return bool
     */
    protected function isAnyDocLocked(array $docIds)
    {
        $docs = Doc::find([
            'conditions' => 'id IN ({docIds:array})',
            'bind' => ['docIds' => $docIds],
        ]);
        foreach ($docs as $doc) {
            if (docBehavior::isLocked($doc->locked)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a validation message and cancel the save.
     *
     * @param  ModelInterface $model
     * @param  string         $field
     * @param  string         $message
     * @return bool
     */
    private function reject($model, $field, $message)
    {
        $model->appendMessage(new Message($message, $field, 'InvalidValue'));
        return false;
    }
}
