<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The `create_issue` action: creates an issue in the run's project. The
 * issue belongs to the project member `ownerId` names (or the workflow's
 * acting user) and is assigned to `assigneeId`, or else to its owner. Its
 * type defaults to the type of the issue the event is about.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CreateIssueAction extends WorkflowAction
{
    public const DEFAULT_PRIORITY = 'medium';

    /**
     * {@inheritdoc}
     */
    public function run(array $config, RunContext $context)
    {
        $shortCode = $context->projectId() === null ? null : $this->records->projectShortCode($context->projectId());
        if ($shortCode === null) {
            throw new WorkflowStepException('The run has no project to create the issue in.');
        }

        $ownerId = $this->user($config, 'ownerId', $context, true);
        $assigneeId = isset($config['assigneeId']) && $config['assigneeId'] !== ''
            ? $this->user($config, 'assigneeId', $context)
            : $ownerId;
        $typeId = $this->setting($config, 'typeId', $context, $this->subjectTypeId($context));
        if (!is_scalar($typeId) || (string) $typeId === '') {
            throw new WorkflowStepException('config.typeId is required when the event is not about an issue.');
        }

        return $this->createRecord('Issue', [
            'subject' => self::text($this->setting($config, 'subject', $context), 'subject'),
            'description' => (string) $this->setting($config, 'description', $context, ''),
            'typeId' => (string) $typeId,
            'priority' => (string) $this->setting($config, 'priority', $context, self::DEFAULT_PRIORITY),
            'projectId' => $context->projectId(),
            'projectShortcode' => $shortCode,
            'owner' => $ownerId,
            'reportedUser' => $ownerId,
            'assignee' => $assigneeId,
        ], $ownerId, $context);
    }

    /**
     * The type of the issue the event is about, if it is about one.
     *
     * @param  RunContext $context
     * @return string|null
     */
    private function subjectTypeId(RunContext $context)
    {
        $subject = $context->subject();
        if ($subject === null || $subject[0] !== 'Issue') {
            return null;
        }
        $issue = $this->records->find('Issue', $subject[1]);

        return $issue ? (string) $issue->readAttribute('typeId') : null;
    }
}
