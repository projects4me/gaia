<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * The actor steps a workflow runs, by node kind. An actor starts its step
 * and the run waits for the actor's answer.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActorCatalog
{
    /**
     * The actor class of each actor node kind.
     */
    private const ACTORS = [
        RunAgentActor::KIND => RunAgentActor::class,
        AskUserActor::KIND => AskUserActor::class,
    ];

    /**
     * @var WorkflowAction[] By kind, built on first use.
     */
    private $actors = [];

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
     * @var ActorSteps
     */
    private $steps;

    /**
     * @param WorkflowRecords    $records
     * @param UserSelector       $users
     * @param ExpressionResolver $expressions
     * @param ActorSteps         $steps
     */
    public function __construct(
        WorkflowRecords $records,
        UserSelector $users,
        ExpressionResolver $expressions,
        ActorSteps $steps
    ) {
        $this->records = $records;
        $this->users = $users;
        $this->expressions = $expressions;
        $this->steps = $steps;
    }

    /**
     * @param  string $kind
     * @return WorkflowAction
     * @throws WorkflowStepException When the kind is no actor.
     */
    public function actor($kind)
    {
        if (!isset($this->actors[$kind])) {
            if (!isset(self::ACTORS[$kind])) {
                throw new WorkflowStepException("Actor {$kind} cannot run yet.");
            }
            $class = self::ACTORS[$kind];
            $this->actors[$kind] = new $class($this->records, $this->users, $this->expressions, $this->steps);
        }

        return $this->actors[$kind];
    }
}
