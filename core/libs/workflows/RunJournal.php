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
 * as JSON text.
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
     * @param  array  $step sequence, nodeId, category, kind, status, port, inputs, outputs, error, startedAt, finishedAt, durationMs
     * @return void
     */
    public function step($runId, array $step)
    {
        $row = new Workflowrunstep();
        $row->id = create_guid();
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
     * Records how a run ended.
     *
     * @param  string      $runId
     * @param  string      $status RunStatus::COMPLETED or FAILED.
     * @param  string|null $error
     * @return void
     */
    public function finish($runId, $status, $error = null)
    {
        $row = Workflowrun::findFirst(['conditions' => 'id = :id:', 'bind' => ['id' => (string) $runId]]);
        if (!$row) {
            return;
        }
        $row->status = (string) $status;
        $row->error = self::textOrNull($error);
        $row->finishedAt = self::now();
        $this->save($row);
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
