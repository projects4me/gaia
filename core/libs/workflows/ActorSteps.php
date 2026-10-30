<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use Gaia\MVC\Models\Workflowactorstep;

/**
 * Reads and writes the actor steps (workflow_actor_steps). A step moves on
 * only from the state its writer expects, in one conditional UPDATE, so a
 * report and a timeout racing for the same step can't both win.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActorSteps
{
    /**
     * The columns transition() may set.
     */
    private const WRITABLE = ['status', 'outcome', 'comment', 'claimedAt', 'finishedAt'];

    /**
     * Records a pending actor step.
     *
     * @param  array $step id (the run step's id), runId, workflowId, nodeId, kind, actorUserId and the kind's settings.
     * @return void
     * @throws \RuntimeException When the row does not save.
     */
    public function create(array $step)
    {
        $row = new Workflowactorstep();
        foreach ($step as $field => $value) {
            $row->writeAttribute($field, $value);
        }
        $row->status = ActorStepStatus::PENDING;
        if (!$row->save()) {
            $messages = array_map(function ($message) {
                return $message->getMessage();
            }, $row->getMessages());
            throw new \RuntimeException('Could not record the actor step: ' . implode(' ', $messages));
        }
    }

    /**
     * @param  string $id
     * @return array|null
     */
    public function find($id)
    {
        $row = Workflowactorstep::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => (string) $id],
        ]);

        return $row ? $row->toArray() : null;
    }

    /**
     * Moves a step on when it is still in one of the expected states.
     *
     * @param  string   $id
     * @param  string[] $from   The states it may move from.
     * @param  array    $values The columns to set (see WRITABLE).
     * @return bool False when another writer moved it first.
     */
    public function transition($id, array $from, array $values)
    {
        $set = [];
        $bind = [];
        foreach ($values as $column => $value) {
            if (!in_array($column, self::WRITABLE, true)) {
                throw new \InvalidArgumentException("{$column} is not an actor step column transition() sets.");
            }
            $set[] = "\"{$column}\" = ?";
            $bind[] = $value;
        }
        $set[] = '"dateModified" = ?';
        $bind[] = RunJournal::now();
        $bind[] = (string) $id;
        $states = implode(', ', array_fill(0, count($from), '?'));
        $bind = array_merge($bind, array_values($from));

        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute(
            'UPDATE workflow_actor_steps SET ' . implode(', ', $set)
            . " WHERE id = ? AND deleted = false AND \"status\" IN ({$states})",
            $bind
        );

        return $db->affectedRows() === 1;
    }

    /**
     * The 'Run agent' steps past their deadline: not claimed by claimBy or
     * not finished by finishBy.
     *
     * @param  string $now As RunJournal::now().
     * @return array[]
     */
    public function overdue($now)
    {
        $rows = Workflowactorstep::find([
            'conditions' => 'deleted = false AND kind = :kind: AND ('
                . '(status = :pending: AND claimBy < :now:) OR (status = :claimed: AND finishBy < :now:))',
            'bind' => [
                'kind' => RunAgentActor::KIND,
                'pending' => ActorStepStatus::PENDING,
                'claimed' => ActorStepStatus::CLAIMED,
                'now' => (string) $now,
            ],
        ]);

        return $rows ? $rows->toArray() : [];
    }

    /**
     * The answered steps among the given ids, with what they were answered.
     *
     * @param  string[] $ids
     * @param  string   $before Only steps last changed before this time (as RunJournal::now()).
     * @return array[]
     */
    public function answered(array $ids, $before)
    {
        if (!$ids) {
            return [];
        }
        $rows = Workflowactorstep::find([
            'conditions' => 'deleted = false AND id IN ({ids:array}) AND status IN ({states:array})'
                . ' AND dateModified < :before:',
            'bind' => [
                'ids' => array_values($ids),
                'states' => [ActorStepStatus::FINISHED, ActorStepStatus::TIMED_OUT],
                'before' => (string) $before,
            ],
        ]);

        return $rows ? $rows->toArray() : [];
    }

    /**
     * The 'Ask a user' steps waiting on a record.
     *
     * @param  string $entityType e.g. issue.
     * @param  string $entityId
     * @return array[]
     */
    public function waitingOn($entityType, $entityId)
    {
        $rows = Workflowactorstep::find([
            'conditions' => 'deleted = false AND kind = :kind: AND status = :pending: AND entityType = :entityType:'
                . ' AND entityId = :entityId:',
            'bind' => [
                'kind' => AskUserActor::KIND,
                'pending' => ActorStepStatus::PENDING,
                'entityType' => strtolower((string) $entityType),
                'entityId' => (string) $entityId,
            ],
            'order' => 'dateCreated',
        ]);

        return $rows ? $rows->toArray() : [];
    }
}
