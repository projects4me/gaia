<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Scoreboard'] = array(
    'tableName' => 'scoreboard',
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_SCOREBOARD_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
        ),
        'userId' => array(
            'name' => 'userId',
            'label' => 'LBL_SCOREBOARD_USER_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
        ),
        'badgeId' => array(
            'name' => 'badgeId',
            'label' => 'LBL_SCOREBOARD_BADGE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false
        ),
        'score' => array(
            'name' => 'score',
            'label' => 'LBL_SCOREBOARD_SCORE',
            'type' => 'int',
            'length' => '100',
            'null' => false
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_SCOREBOARD_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_SCOREBOARD_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_SCOREBOARD_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_SCOREBOARD_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_SCOREBOARD_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_SCOREBOARD_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_SCOREBOARD_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
            'default' => 0,
            'fts' => true
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
    'functions' => array(),
    'relationships' => array(
        'hasOne' => array(
            'userBadgeLevel' => array(
                'primaryKey' => 'badgeId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Badgelevel',
                'relatedKey' => 'badgeId',
                'conditionExclusive' => 'Scoreboard.badgeId = userBadgeLevel.badgeId AND 
                                         Scoreboard.score > userBadgeLevel.min_criteria AND
                                         Scoreboard.score < userBadgeLevel.max_criteria'
            ),
            'userBadge' => array(
                'primaryKey' => 'id',
                'relatedModel' => '\\Gaia\\MVC\\Models\\Badge',
                'relatedKey' => 'id',
                'conditionExclusive' => 'userBadge.id = userBadgeLevel.badgeId'
            )
        ),
        'hasMany' => array(
            'users' => array(
                'primaryKey' => 'userId',
                'relatedModel' => '\\Gaia\\MVC\\Models\\User',
                'relatedKey' => 'id',
            )
        )
    ),
    'behaviors' => array(
        'auditBehavior',
        'dateCreatedBehavior',
        'dateModifiedBehavior',
        'createdUserBehavior',
        'modifiedUserBehavior',
        'softDeleteBehavior'
    )
);

return $models;
