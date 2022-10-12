<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$models['Userlatestissue'] = array(
    'tableName' => 'user_latest_issues',
    'viewSql' => 'SELECT IssueSubQuery.*,Activity.dateCreated as lastActivityDate, Project.shortCode as projectShortCode from 
                    (select Issue.id, Issue.subject, Issue.issueNumber, Issue.status, Issue.projectId, Issue.createdUser as userId, Issue.dateCreated from issues as Issue where "1" = checkIssueIsLatest(Issue.createdUser, Issue.id) 
                    ) IssueSubQuery left join activities as Activity on Activity.relatedId = IssueSubQuery.id AND Activity.relatedTo = "issue" AND Activity.createdUser = IssueSubQuery.userId
                    left join projects as Project on Project.id = IssueSubQuery.projectId
                  GROUP BY CONCAT(IssueSubQuery.userId,IssueSubQuery.id)',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'type' => 'varchar',
            'null' => false,
        ),
        'subject' => array(
            'name' => 'subject',
            'type' => 'varchar',
            'null' => false,
        ),
        'issueNumber' => array(
            'name' => 'issueNumber',
            'type' => 'varchar',
            'null' => false,
        ),
        'projectShortCode' => array(
            'name' => 'projectShortCode',
            'type' => 'varchar',
            'null' => false,
        ),
        'status' => array(
            'name' => 'status',
            'type' => 'varchar',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'type' => 'varchar',
            'null' => false,
        ),
        'lastActivityDate' => array(
            'name' => 'lastActivityDate',
            'type' => 'varchar',
            'null' => false,
        ),
        'userId' => array(
            'name' => 'userId',
            'type' => 'varchar',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'type' => 'varchar',
            'null' => false,
        )
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
    'functions' => array(
        'checkIssueIsLatest' => array(
            'functionName' => 'checkIssueIsLatest',
            'returnType' => 'INT(1)',
            'parameters' => 'userId VARCHAR(36), issueId VARCHAR(36)',
            'statement' => 'BEGIN
                                create TEMPORARY TABLE IF NOT EXISTS temp_issues (
                                id VARCHAR(36),
                                createdUser VARCHAR(36)
                                );
                                select * from (SELECT @temp1 := IF(COUNT(t.id) = 1,1,0) as temp from
                                (select Issue.id FROM temp_issues i
                                where Issue.id = issueId) t 
                                where t.id = issueId) t2 into @myvar;
                                IF @temp1 = 0 THEN
                                select * from (SELECT @temp2 := IF(COUNT(t.id) = 1,1,0) as temp from
                                (select Issue.id FROM issues i
                                left join activities a on Activity.relatedId = Issue.id and Activity.relatedTo = "issue"
                                where Issue.createdUser = userId
                                GROUP BY CONCAT(Issue.createdUser, Issue.id)
                                ORDER BY Activity.dateCreated DESC LIMIT 5) t 
                                where t.id = issueId) t2 into @myvar2;
                                ELSEIF @temp1 = 1 THEN
                                SET	@temp2 = 1;
                                END IF;
                                IF @temp2 = 1 AND @temp1 != 1 THEN 
                                INSERT INTO temp_issues (id, createdUser)
                                select Issue.id,Issue.createdUser FROM issues i
                                left join activities a on Activity.relatedId = Issue.id and Activity.relatedTo = "issue"
                                where Issue.createdUser = userId
                                GROUP BY CONCAT(Issue.createdUser, Issue.id)
                                ORDER BY Activity.dateCreated DESC LIMIT 5;
                                END IF;
                                RETURN @temp2;
                            END;'
        )
    )
);

return $models;
