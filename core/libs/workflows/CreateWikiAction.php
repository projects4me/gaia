<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The `create_wiki` action: creates a wiki page in the run's project,
 * belonging to the project member `ownerId` names or to the workflow's
 * acting user.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CreateWikiAction extends WorkflowAction
{
    public const STATUS = 'published';

    /**
     * {@inheritdoc}
     */
    public function run(array $config, RunContext $context)
    {
        if ($context->projectId() === null) {
            throw new WorkflowStepException('The run has no project to create the wiki page in.');
        }

        return $this->createRecord('Wiki', [
            'name' => self::text($this->setting($config, 'title', $context), 'title'),
            'markUp' => (string) $this->setting($config, 'body', $context, ''),
            'projectId' => $context->projectId(),
            'status' => self::STATUS,
            'locked' => 0,
            'upvotes' => 0,
        ], $this->user($config, 'ownerId', $context, true), $context);
    }
}
