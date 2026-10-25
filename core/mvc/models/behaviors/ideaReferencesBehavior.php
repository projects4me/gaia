<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\MVC\Models\Ideaplan;
use Gaia\MVC\Models\Issue;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior checks the references of a record that belongs to an idea
 * (idea messages, plan issues): its ideaplanId must name a plan of the same
 * idea, and its issueId, when the model has one, must name an existing issue.
 * An empty reference passes only when the model's metadata declares the
 * field nullable (an idea message's plan), not when it is required (a plan
 * issue's plan and issue).
 * The issue may be in another project; whether the user may link it is an
 * ACL decision (see the model's acl references).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ideaReferencesBehavior extends Behavior implements BehaviorInterface
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
        return $this->checkPlan($model) && $this->checkIssue($model);
    }

    /**
     * Only changed references are checked again, so a later save (e.g. a soft
     * delete) does not fail because a referenced plan was removed since.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        $snapshot = is_array($snapshot) ? $snapshot : [];

        if (($this->changed($model, $snapshot, 'ideaId') || $this->changed($model, $snapshot, 'ideaplanId'))
            && !$this->checkPlan($model)
        ) {
            return false;
        }

        return !$this->changed($model, $snapshot, 'issueId') || $this->checkIssue($model);
    }

    /**
     * Reject an ideaplanId that is not a plan of the record's idea, or an
     * empty one where the field is required.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkPlan($model)
    {
        $planId = isset($model->ideaplanId) ? (string) $model->ideaplanId : '';
        if ($planId === '') {
            return $this->checkEmpty($model, 'ideaplanId');
        }
        if ($this->planBelongsToIdea($planId, (string) $model->ideaId)) {
            return true;
        }

        return $this->reject($model, 'ideaplanId', 'ideaplanId must be a plan of the same idea.');
    }

    /**
     * Reject an issueId that names no issue, or an empty one where the field
     * is required.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkIssue($model)
    {
        $issueId = isset($model->issueId) ? (string) $model->issueId : '';
        if ($issueId === '') {
            return $this->checkEmpty($model, 'issueId');
        }
        if ($this->issueExists($issueId)) {
            return true;
        }

        return $this->reject($model, 'issueId', 'issueId must be an existing issue.');
    }

    /**
     * An empty reference passes unless the model's metadata declares the
     * field not null. A model without the field (an idea message has no
     * issueId) passes too.
     *
     * @param  ModelInterface $model
     * @param  string         $field
     * @return bool
     */
    protected function checkEmpty($model, $field)
    {
        $metadata = $model->getMetaData();
        $required = isset($metadata['fields'][$field]['null']) && $metadata['fields'][$field]['null'] === false;

        return $required ? $this->reject($model, $field, $field . ' is required.') : true;
    }

    /**
     * Whether the value of a field differs from the stored one.
     *
     * @param  ModelInterface $model
     * @param  array          $snapshot
     * @param  string         $field
     * @return bool
     */
    protected function changed($model, array $snapshot, $field)
    {
        $current = isset($model->{$field}) ? (string) $model->{$field} : '';
        $stored = isset($snapshot[$field]) ? (string) $snapshot[$field] : '';

        return $current !== $stored;
    }

    /**
     * @param  string $planId
     * @param  string $ideaId
     * @return bool
     */
    protected function planBelongsToIdea($planId, $ideaId)
    {
        return (bool) Ideaplan::findFirst([
            'conditions' => 'id = :id: AND ideaId = :ideaId: AND deleted = false',
            'bind' => ['id' => $planId, 'ideaId' => $ideaId],
        ]);
    }

    /**
     * @param  string $issueId
     * @return bool
     */
    protected function issueExists($issueId)
    {
        return (bool) Issue::findFirst([
            // issues.deleted is a smallint, unlike the idea tables' boolean.
            'conditions' => 'id = :id: AND deleted = 0',
            'bind' => ['id' => $issueId],
        ]);
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
