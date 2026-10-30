<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

use Gaia\MVC\Models\Workflowrun;
use Gaia\MVC\Models\Workflowrunstep;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Writes the run history: one workflow_runs row per run and one
 * workflow_run_steps row per step it took. Inputs and outputs are stored
 * as JSON text. A run whose actor step waits is left waiting, and is taken
 * on again once the step has an answer.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RunJournal
{
    /**
     * Records a run as running.
     *
     * @param  array $run workflowId, workflowVersion, triggerNodeId, eventName, eventId, projectId, parentRunId, depth, inputs
     * @return string The run id.
     */
    public function start(array $run)
    {
        $row = new Workflowrun();
        $row->id = create_guid();
        $row->workflowId = (string) $run['workflowId'];
        $row->workflowVersion = (int) $run['workflowVersion'];
        $row->triggerNodeId = (string) $run['triggerNodeId'];
        $row->eventName = self::textOrNull($run['eventName']);
        $row->eventId = self::textOrNull($run['eventId']);
        $row->projectId = self::textOrNull($run['projectId']);
        $row->parentRunId = self::textOrNull($run['parentRunId']);
        $row->depth = (int) $run['depth'];
        $row->status = RunStatus::RUNNING;
        $row->inputs = json_encode($run['inputs']);
        $row->startedAt = self::now();
        $this->save($row);

        return (string) $row->id;
    }

    /**
     * Records a step.
     *
     * @param  string $runId
     * @param  array  $step id (optional), sequence, nodeId, category, kind, status, port, inputs, outputs, error, startedAt, finishedAt, durationMs
     * @return void
     */
    public function step($runId, array $step)
    {
        $row = new Workflowrunstep();
        $row->id = empty($step['id']) ? create_guid() : (string) $step['id'];
        $row->runId = (string) $runId;
        $row->sequence = (int) $step['sequence'];
        $row->nodeId = (string) $step['nodeId'];
        $row->category = (string) $step['category'];
        $row->kind = (string) $step['kind'];
        $row->status = (string) $step['status'];
        $row->port = self::textOrNull($step['port']);
        $row->inputs = json_encode($step['inputs']);
        $row->outputs = json_encode($step['outputs']);
        $row->error = self::textOrNull($step['error']);
        $row->startedAt = $step['startedAt'];
        $row->finishedAt = $step['finishedAt'];
        $row->durationMs = (int) $step['durationMs'];
        $this->save($row);
    }

    /**
     * Records how a run ended, or that it waits for an actor.
     *
     * @param  string      $runId
     * @param  string      $status RunStatus::COMPLETED, FAILED or WAITING.
     * @param  string|null $error
     * @return void
     */
    public function finish($runId, $status, $error = null)
    {
        $row = $this->runRow($runId);
        if (!$row) {
            return;
        }
        $row->status = (string) $status;
        $row->error = self::textOrNull($error);
        $row->finishedAt = $status === RunStatus::WAITING ? null : self::now();
        $this->save($row);
    }

    /**
     * A waiting step and its run, both as stored; null when the step is not
     * waiting (any more) or its run is not.
     *
     * @param  string $stepId
     * @return array|null {step, run}
     */
    public function waitingStep($stepId)
    {
        $step = Workflowrunstep::findFirst([
            'conditions' => 'id = :id: AND status = :status: AND deleted = false',
            'bind' => ['id' => (string) $stepId, 'status' => RunStatus::WAITING],
        ]);
        $run = $step ? $this->runRow($step->runId) : null;
        if (!$run || $run->status !== RunStatus::WAITING) {
            return null;
        }

        return ['step' => $step->toArray(), 'run' => $run->toArray()];
    }

    /**
     * Records the answer to a waiting step and sets its run running again.
     * The step moves on only while it still waits, in one conditional
     * UPDATE, so two answers racing for it can't both carry the run on.
     *
     * @param  string $stepId
     * @param  string $port    The port the run goes on by.
     * @param  array  $outputs Added to what the step recorded when it started.
     * @return bool False when the step no longer waits.
     */
    public function answer($stepId, $port, array $outputs)
    {
        $step = Workflowrunstep::findFirst(['conditions' => 'id = :id:', 'bind' => ['id' => (string) $stepId]]);
        if (!$step) {
            return false;
        }
        $started = json_decode((string) $step->outputs, true);
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute(
            'UPDATE workflow_run_steps SET "status" = ?, "port" = ?, "outputs" = ?, "finishedAt" = ?,'
            . ' "durationMs" = ?, "dateModified" = ? WHERE id = ? AND "status" = ?',
            [
                RunStatus::COMPLETED,
                (string) $port,
                json_encode((is_array($started) ? $started : []) + $outputs),
                self::now(),
                max(0, (time() - strtotime($step->startedAt . ' UTC')) * 1000),
                self::now(),
                (string) $stepId,
                RunStatus::WAITING,
            ]
        );
        if ($db->affectedRows() !== 1) {
            return false;
        }

        $run = $this->runRow($step->runId);
        if ($run) {
            $run->status = RunStatus::RUNNING;
            $this->save($run);
        }

        return true;
    }

    /**
     * The ids of the actor steps still waiting in the run history.
     *
     * @return string[]
     */
    public function waitingActorStepIds()
    {
        $rows = Workflowrunstep::find([
            'columns' => 'id',
            'conditions' => 'status = :status: AND category = :category: AND deleted = false',
            'bind' => ['status' => RunStatus::WAITING, 'category' => NodeKindCatalog::ACTOR],
        ]);

        return $rows ? array_column($rows->toArray(), 'id') : [];
    }

    /**
     * The highest step sequence a run has used.
     *
     * @param  string $runId
     * @return int
     */
    public function lastSequence($runId)
    {
        return (int) Workflowrunstep::maximum([
            'column' => 'sequence',
            'conditions' => 'runId = :runId:',
            'bind' => ['runId' => (string) $runId],
        ]);
    }

    /**
     * The current time as stored in datetime columns.
     *
     * @return string
     */
    public static function now()
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * @param  string $runId
     * @return Workflowrun|false
     */
    private function runRow($runId)
    {
        return Workflowrun::findFirst(['conditions' => 'id = :id:', 'bind' => ['id' => (string) $runId]]);
    }

    /**
     * @param  \Phalcon\Mvc\ModelInterface $row
     * @return void
     * @throws \RuntimeException When the row does not save.
     */
    private function save($row)
    {
        if (!$row->save()) {
            $messages = array_map(function ($message) {
                return $message->getMessage();
            }, $row->getMessages());
            throw new \RuntimeException('Could not write the workflow run history: ' . implode(' ', $messages));
        }
    }

    /**
     * @param  mixed $value
     * @return string|null
     */
    private static function textOrNull($value)
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
