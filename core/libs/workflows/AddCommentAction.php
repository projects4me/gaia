<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The `add_comment` action: comments on the record the event is about (on
 * the commented record when the event is about a comment). The comment
 * belongs to the project member `ownerId` names, or to the workflow's
 * acting user.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AddCommentAction extends WorkflowAction
{
    /**
     * {@inheritdoc}
     */
    public function run(array $config, RunContext $context)
    {
        list($relatedTo, $relatedId) = $this->commentTarget($this->subject($context));

        return $this->createRecord('Comment', [
            'comment' => self::text($this->setting($config, 'body', $context), 'body'),
            'relatedTo' => $relatedTo,
            'relatedId' => $relatedId,
        ], $this->user($config, 'ownerId', $context, true), $context);
    }
}
