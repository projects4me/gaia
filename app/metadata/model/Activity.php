<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Activity'] = array(
    'tableName' => 'activities',
    'fts' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_ACTIVITIES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_ACTIVITIES_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
            'fts' => true
        ),
        'description' => array(
            'name' => 'description',
            'label' => 'LBL_ACTIVITIES_DESCRIPTION',
            'type' => 'text',
            'null' => true,
            'fts' => true
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_ACTIVITIES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_ACTIVITIES_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'relatedTo' => array(
            'name' => 'relatedTo',
            'label' => 'LBL_ACTIVITIES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '30',
            'null' => false,
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'label' => 'LBL_ACTIVITIES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_ACTIVITIES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'fts' => true
        ),
        'type' => array(
            'name' => 'type',
            'label' => 'LBL_ACTIVITIES_TYPE',
            'type' => 'varchar',
            'length' => '15',
            'null' => false,
            'fts' => true
        ),
        'relatedActivity' => array(
            'name' => 'relatedActivity',
            'label' => 'LBL_ACTIVITIES_RELATED_ACTIVITY',
            'type' => 'varchar',
            'length' => '15',
            'null' => true,
        ),
        'relatedActivityId' => array(
            'name' => 'relatedActivityId',
            'label' => 'LBL_ACTIVITIES_RELATED_ACTIVITY_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'relatedActivityModule' => array(
            'name' => 'relatedActivityModule',
            'label' => 'LBL_ACTIVITIES_RELATED_ACTIVITY_MODULE',
            'type' => 'varchar',
            'length' => '15',
            'null' => true,
        )
    ),
    'indexes' => array(
        'id' => 'primary',
        'relatedId' => 'INDEX',
        'relatedTo' => 'INDEX',
        'createdUser' => 'INDEX',
        'dateCreated' => 'INDEX'
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(
        'addLastActivityDate' => array(
            'triggerName' => 'add_last_activity_date',
            'eventType' => 'AFTER INSERT',
            'statement' => 'BEGIN
                                IF NEW.relatedTo = "issue" THEN
                                UPDATE issues as Issue SET Issue.lastActivityDate = NEW.dateCreated where Issue.id = NEW.relatedId;
                                ELSEIF NEW.relatedTo = "project" THEN
                                UPDATE memberships as Membership SET Membership.lastActivityDate = NEW.dateCreated where Membership.projectId = NEW.relatedId AND Membership.userId = NEW.createdUser;
                                END IF;
                            END;'
        )
    ),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'createdBy' => array(
                'primaryKey' => 'createdUser',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id'
            ),
            'project' => array(
                'primaryKey' => 'relatedId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Project',
                'relatedKey' => 'id',
                'condition' => 'Activity.relatedTo = "project"'
            )
        )
    ),
    'behaviors' => array(
        'auditBehavior',
        'createdUserBehavior',
        'dateCreatedBehavior',
        'softDeleteBehavior'
   )
);

return $models;
