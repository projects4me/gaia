<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userlatestproject'] = array(
    'tableName' => 'user_latest_projects',
    'viewSql' => 'SELECT SubQuery2.*, Activity.dateCreated as lastActivityDate from 
                    (select * from 
                        (select Membership.userId, Project.id as id, Membership.createdUser, Membership.id as membershipId,
                            (select COUNT(Issue.id) from issues as Issue where Issue.projectId = Membership.projectId) as totalIssues,
                            (select COUNT(Issue.id) as totalIssues from issues as Issue left join issue_statuses as IssueStatuses on IssueStatuses.id = Issue.statusId where Issue.projectId = Membership.projectId AND IssueStatuses.done="1") as closedIssues,
                            Project.description, Project.status, Project.name, Project.shortCode
                            from projects as Project
                            inner join memberships as Membership on "1"  = checkProjectIsLatest(Membership.userId, Project.id) AND Membership.projectId = Project.id
                            ORDER BY Membership.userId DESC) SubQuery1) SubQuery2
                inner join activities as Activity on Activity.relatedId = SubQuery2.id AND Activity.relatedTo ="project" AND Activity.createdUser = SubQuery2.userId
                GROUP BY CONCAT (SubQuery2.userId, SubQuery2.membershipId);',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
        ),
        'name' => array(
            'name' => 'name',
            'type' => 'varchar',
            'null' => false,
        ),
        'description' => array(
            'name' => 'description',
            'type' => 'varchar',
            'null' => false,
        ),
        'status' => array(
            'name' => 'status',
            'type' => 'varchar',
            'null' => false,
        ),
        'lastActivityDate' => array(
            'name' => 'lastActivityDate',
            'type' => 'varchar',
            'null' => false,
        ),
        'shortCode' => array(
            'name' => 'shortCode',
            'type' => 'varchar',
            'null' => false,
        ),
        'userId' => array(
            'name' => 'userId',
            'type' => 'int',
            'null' => false,
        ),
        'closedIssues' => array(
            'name' => 'closedIssues',
            'type' => 'int',
            'null' => false,
        ),
        'totalIssues' => array(
            'name' => 'totalIssues',
            'type' => 'int',
            'null' => false,
        ),

    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
    'functions' => array()
);

return $models;
