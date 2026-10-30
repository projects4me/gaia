<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The action each action node kind runs. `notify` and `call_tool` have no
 * channel to run on yet, so a step of either kind fails with that reason.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActionCatalog
{
    /**
     * The action class of each kind that can run.
     */
    private const ACTIONS = [
        'assign' => AssignAction::class,
        'set_field' => SetFieldAction::class,
        'set_status' => SetStatusAction::class,
        'add_comment' => AddCommentAction::class,
        'create_issue' => CreateIssueAction::class,
        'create_wiki' => CreateWikiAction::class,
    ];

    /**
     * @var WorkflowAction[] kind => action, built on first use
     */
    private $actions = [];

    /**
     * @var WorkflowRecords
     */
    private $records;

    /**
     * @var UserSelector
     */
    private $users;

    /**
     * @var ExpressionResolver
     */
    private $expressions;

    /**
     * @param WorkflowRecords    $records
     * @param UserSelector       $users
     * @param ExpressionResolver $expressions
     */
    public function __construct(WorkflowRecords $records, UserSelector $users, ExpressionResolver $expressions)
    {
        $this->records = $records;
        $this->users = $users;
        $this->expressions = $expressions;
    }

    /**
     * The action of a kind.
     *
     * @param  string $kind
     * @return WorkflowAction
     * @throws WorkflowStepException When the kind cannot run yet.
     */
    public function action($kind)
    {
        if (!isset(self::ACTIONS[$kind])) {
            throw new WorkflowStepException("Action {$kind} cannot run yet.");
        }
        if (!isset($this->actions[$kind])) {
            $class = self::ACTIONS[$kind];
            $this->actions[$kind] = new $class($this->records, $this->users, $this->expressions);
        }

        return $this->actions[$kind];
    }
}
