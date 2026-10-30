<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * Workflowactorstep metadata: a workflow step that waits for an actor. A
 * 'Run agent' step waits for the agent's runtime to claim it and report
 * its outcome; an 'Ask a user' step waits for the user's reply or a
 * reassignment. The id is the id of the workflow_run_steps row it belongs
 * to, so the stepId in workflow.agent.run.requested reads it directly.
 *
 * Only the actor sees and updates its steps (record scope by actorUserId);
 * the runtime claims and reports through PATCH.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
$models['Workflowactorstep'] = array(
    'tableName' => 'workflow_actor_steps',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true,
        ),
        'runId' => array(
            'name' => 'runId',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_RUN_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'workflowId' => array(
            'name' => 'workflowId',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_WORKFLOW_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'nodeId' => array(
            'name' => 'nodeId',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_NODE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'kind' => array(
            'name' => 'kind',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_KIND',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'actorUserId' => array(
            'name' => 'actorUserId',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_ACTOR_USER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'agentId' => array(
            'name' => 'agentId',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_AGENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_PROJECT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'entityType' => array(
            'name' => 'entityType',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_ENTITY_TYPE',
            'type' => 'varchar',
            'length' => '50',
            'null' => true,
        ),
        'entityId' => array(
            'name' => 'entityId',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_ENTITY_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'prompt' => array(
            'name' => 'prompt',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_PROMPT',
            'type' => 'text',
            'null' => true,
        ),
        'session' => array(
            'name' => 'session',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_SESSION',
            'type' => 'varchar',
            'length' => '20',
            'null' => true,
        ),
        'model' => array(
            'name' => 'model',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_MODEL',
            'type' => 'varchar',
            'length' => '100',
            'null' => true,
        ),
        'maxTurns' => array(
            'name' => 'maxTurns',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_MAX_TURNS',
            'type' => 'int',
            'null' => true,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_STATUS',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
        ),
        'outcome' => array(
            'name' => 'outcome',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_OUTCOME',
            'type' => 'varchar',
            'length' => '50',
            'null' => true,
        ),
        'comment' => array(
            'name' => 'comment',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_COMMENT',
            'type' => 'text',
            'null' => true,
        ),
        'claimBy' => array(
            'name' => 'claimBy',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_CLAIM_BY',
            'type' => 'datetime',
            'null' => true,
        ),
        'finishBy' => array(
            'name' => 'finishBy',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_FINISH_BY',
            'type' => 'datetime',
            'null' => true,
        ),
        'claimedAt' => array(
            'name' => 'claimedAt',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_CLAIMED_AT',
            'type' => 'datetime',
            'null' => true,
        ),
        'finishedAt' => array(
            'name' => 'finishedAt',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_FINISHED_AT',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_WORKFLOW_ACTOR_STEPS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'runId' => 'index',
        'actorUserId' => 'index',
        'status' => 'index',
        'entityId' => 'index',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'run' => array(
                'primaryKey' => 'runId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflowrun',
                'relatedKey' => 'id'
            ),
            'agent' => array(
                'primaryKey' => 'agentId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Agent',
                'relatedKey' => 'id'
            ),
            'actor' => array(
                'primaryKey' => 'actorUserId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
        ),
    ),
    'behaviors' => array(
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'softDeleteBehavior'
    ),
    'acl' => [
        // Record scope by actor: an agent's runtime sees only its own steps.
        'assignment' => [
            'field' => 'actorUserId',
            'condition' => 'Workflowactorstep.actorUserId=:userId:'
        ]
    ],
);

return $models;
