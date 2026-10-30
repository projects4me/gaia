<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * One step of a workflow run: the node, the port it took, its resolved inputs, outputs, error and timings.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Metadata
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */

$models['Workflowrunstep'] = array(
    'tableName' => 'workflow_run_steps',
    'fts' => false,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true,
        ),
        'runId' => array(
            'name' => 'runId',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_RUN_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'sequence' => array(
            'name' => 'sequence',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_SEQUENCE',
            'type' => 'int',
            'null' => false,
            'default' => 0,
        ),
        'nodeId' => array(
            'name' => 'nodeId',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_NODE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'category' => array(
            'name' => 'category',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_CATEGORY',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
        ),
        'kind' => array(
            'name' => 'kind',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_KIND',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_STATUS',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
        ),
        'port' => array(
            'name' => 'port',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_PORT',
            'type' => 'varchar',
            'length' => '50',
            'null' => true,
        ),
        'inputs' => array(
            'name' => 'inputs',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_INPUTS',
            'type' => 'text',
            'null' => true,
        ),
        'outputs' => array(
            'name' => 'outputs',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_OUTPUTS',
            'type' => 'text',
            'null' => true,
        ),
        'error' => array(
            'name' => 'error',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_ERROR',
            'type' => 'text',
            'null' => true,
        ),
        'startedAt' => array(
            'name' => 'startedAt',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_STARTED_AT',
            'type' => 'datetime',
            'null' => true,
        ),
        'finishedAt' => array(
            'name' => 'finishedAt',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_FINISHED_AT',
            'type' => 'datetime',
            'null' => true,
        ),
        'durationMs' => array(
            'name' => 'durationMs',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_DURATION_MS',
            'type' => 'int',
            'null' => false,
            'default' => 0,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_WORKFLOW_RUN_STEPS_DELETED',
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
        'nodeId' => 'index',
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
            'node' => array(
                'primaryKey' => 'nodeId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Workflownode',
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
        'groups' => ['Project'],
        'groupKeys' => [
            'Project' => [
                'paths' => [
                    [
                        'relatedModel' => 'Workflowrun',
                        'localKey' => 'runId',
                        'relatedKey' => 'id',
                        'projectField' => 'projectId',
                    ],
                ],
            ],
        ],
    ],
);

return $models;
