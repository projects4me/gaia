<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The `set_status` action: moves the issue the event is about to one of
 * its project's issue statuses, named by id or name.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SetStatusAction extends WorkflowAction
{
    /**
     * {@inheritdoc}
     */
    public function run(array $config, RunContext $context)
    {
        $status = self::text($this->setting($config, 'status', $context), 'status');
        $record = $this->subject($context);
        if ($this->describe($record)['entityType'] !== 'Issue') {
            throw new WorkflowStepException('Only an issue has a status to set.');
        }

        $row = $this->records->issueStatus((string) $record->readAttribute('projectId'), $status);
        if ($row === null) {
            throw new WorkflowStepException("The project has no issue status {$status}.");
        }

        return $this->updateSubject($record, ['statusId' => $row['id'], 'status' => $row['name']], $context);
    }
}
