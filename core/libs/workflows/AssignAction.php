<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The `assign` action: assigns the record the event is about to a project
 * member.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AssignAction extends WorkflowAction
{
    /**
     * {@inheritdoc}
     */
    public function run(array $config, RunContext $context)
    {
        $record = $this->subject($context);
        if (!array_key_exists('assignee', $this->fields($record))) {
            throw new WorkflowStepException('A ' . $this->describe($record)['entityType'] . ' has no assignee.');
        }

        return $this->updateSubject($record, ['assignee' => $this->user($config, 'assigneeId', $context)], $context);
    }
}
