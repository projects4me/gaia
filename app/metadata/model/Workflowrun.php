<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * One run of a workflow for an event: written by the workflow engine (RunJournal), read through the API.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Metadata
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */

$models['Workflowrun'] = array(
    'tableName' => 'workflow_runs',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_WORKFLOW_RUNS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true,
        ),
        'workflowId' => array(
            'name' => 'workflowId',
            'label' => 'LBL_WORKFLOW_RUNS_WORKFLOW_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'workflowVersion' => array(
            'name' => 'workflowVersion',
            'label' => 'LBL_WORKFLOW_RUNS_WORKFLOW_VERSION',
            'type' => 'int',
            'null' => false,
            'default' => 0,
        ),
        'triggerNodeId' => array(
            'name' => 'triggerNodeId',
            'label' => 'LBL_WORKFLOW_RUNS_TRIGGER_NODE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'eventName' => array(
            'name' => 'eventName',
            'label' => 'LBL_WORKFLOW_RUNS_EVENT_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => true,
        ),
        'eventId' => array(
            'name' => 'eventId',
            'label' => 'LBL_WORKFLOW_RUNS_EVENT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'entityType' => array(
            'name' => 'entityType',
            'label' => 'LBL_WORKFLOW_RUNS_ENTITY_TYPE',
            'type' => 'varchar',
            'length' => '50',
            'null' => true,
        ),
        'entityId' => array(
            'name' => 'entityId',
            'label' => 'LBL_WORKFLOW_RUNS_ENTITY_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_WORKFLOW_RUNS_PROJECT_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'parentRunId' => array(
            'name' => 'parentRunId',
            'label' => 'LBL_WORKFLOW_RUNS_PARENT_RUN_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'depth' => array(
            'name' => 'depth',
            'label' => 'LBL_WORKFLOW_RUNS_DEPTH',
            'type' => 'int',
            'null' => false,
            'default' => 0,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_WORKFLOW_RUNS_STATUS',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
        ),
        'inputs' => array(
            'name' => 'inputs',
            'label' => 'LBL_WORKFLOW_RUNS_INPUTS',
            'type' => 'text',
            'null' => true,
        ),
        'error' => array(
            'name' => 'error',
            'label' => 'LBL_WORKFLOW_RUNS_ERROR',
            'type' => 'text',
            'null' => true,
        ),
        'startedAt' => array(
            'name' => 'startedAt',
            'label' => 'LBL_WORKFLOW_RUNS_STARTED_AT',
            'type' => 'datetime',
            'null' => true,
        ),
        'finishedAt' => array(
            'name' => 'finishedAt',
            'label' => 'LBL_WORKFLOW_RUNS_FINISHED_AT',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_WORKFLOW_RUNS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_WORKFLOW_RUNS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_WORKFLOW_RUNS_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'acl' => false,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
        'workflowId' => 'index',
        'projectId' => 'index',
        'entityId' => 'index',
        'parentRunId' => 'index',
        'status' => 'index',
    ),
    'foriegnKeys' => array(

    ),
    'triggers' => array(

    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'workflow' => array(
                'primaryKey' => 'workflowId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflow',
                'relatedKey' => 'id'
            ),
            'project' => array(
                'primaryKey' => 'projectId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Project',
                'relatedKey' => 'id'
            ),
            'parentRun' => array(
                'primaryKey' => 'parentRunId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflowrun',
                'relatedKey' => 'id'
            ),
        ),
        'hasMany' => array(
            'steps' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflowrunstep',
                'relatedKey' => 'runId'
            ),
        ),
    ),
    'behaviors' => array(
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'softDeleteBehavior'
    ),
    'acl' => [
        'groups' => ['Project'],
    ],
);

return $models;
