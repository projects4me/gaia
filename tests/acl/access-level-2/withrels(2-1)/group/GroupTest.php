<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Tests\Gaia\Acl\AccessLevel2\WithRels2_1;

/**
 * This class is responsible to test the group model with access level 2 on model and 1 on its relationships.
 * 
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class GroupTest extends \Tests\Gaia\Acl\BaseGroup
{
    /**
     * This method is used to setup some pre-conditions before the loading of any test case.
     * 
     * @return void
     */    
    public static function setUpBeforeClass(): void
    {
        self::$accessLevel = '2';
        self::$testType = 'withrels(2-1)';
        parent::setUpBeforeClass();
    }

    /**
     * This is the data provider.
     * 
     * @return array
     */
    public function data()
    {
        return [
            [
                [
                    "fields" => array(),
                    "rels" => array('issues'),
                    "where" => "",
                    "sort" => "",
                    "order" => "DESC",
                    "limit" => 21,
                    "offset" => 0,
                    "groupBy" => array(),
                    "count" => "",
                    "having" => "",
                    "addRelFields" => false
                ],
                'Project.Read',
                'Project',
                '\\Gaia\\MVC\\Models\\Project',
                    array(
                    'data' => [
                        [
                            "type" => "Project",
                            "id" => "p-acl-1",
                            "attributes" => array(
                                "name" => "test-project-1",
                                "dateCreated" => "2017-04-07 13:03:06",
                                "dateModified" => "2017-04-07 13:03:06",
                                "createdUser" => "u-acl-1",
                                "createdUserName" => "acluser1",
                                "modifiedUser" => "u-acl-1",
                                "modifiedUserName" => "acluser1",
                                "assignee" => "u-acl-1",
                                "deleted" => "0",
                                "description" => "This project is created for integration testing.",
                                "startDate" => "2017-04-07",
                                "endDate" => "2018-04-07",
                                "shortCode" => "pac1",
                                "status" => "in_progress",
                                "estimatedBudget" => null,
                                "spentBudget" => null,
                                "type" => "software",
                                "scope" => null,
                                "vision" => null,
                                "done" => "0"
                            ),
                            'relationships' => array(
                                'issues' => array(
                                    'data' => array(
                                        [
                                            "type" => "issue",
                                            "id" => 'i-acl-1'
                                        ],
                                        [
                                            "type" => "issue",
                                            "id" => 'i-acl-2'
                                        ]
                                    )
                                )
                            )
                        ]
                    ],
                    'included' => array(
                        [
                            "type" => "issue",
                            "id" => "i-acl-1",
                            "attributes" => array(
                                "subject" => "acl-issue-1",
                                "deleted" => "0",
                                "createdUser" => "u-acl-1",
                                'owner' => 'u-acl-1',
                                'assignee' => 'u-acl-1',
                                'reportedUser' => 'u-acl-1',
                                "modifiedUser" => "u-acl-1",
                                'issueNumber' => '100000',
                                'priority' => 'medium',
                                'projectId' => 'p-acl-1',
                                'typeId' => '1',
                                "createdUserName" => "acluser1",
                                "modifiedUserName" => "acluser1",
                                'dateCreated' => null,
                                'dateModified' => null,
                                'lastActivityDate' => null,
                                'description' => null,
                                'endDate' => null,
                                'startDate' => null,
                                'status' => null,
                                'statusId' => null,
                                'milestoneId' => null,
                                'parentId' => null,
                                'conversationRoomId' => null
                            )
                        ],
                        [
                            "type" => "issue",
                            "id" => "i-acl-2",
                            "attributes" => array(
                                "subject" => "acl-issue-2",
                                "deleted" => "0",
                                "createdUser" => "u-acl-1",
                                'owner' => 'u-acl-1',
                                'assignee' => 'u-acl-1',
                                'reportedUser' => 'u-acl-1',
                                "modifiedUser" => "u-acl-1",
                                'issueNumber' => '100001',
                                'priority' => 'medium',
                                'projectId' => 'p-acl-1',
                                'typeId' => '1',
                                "createdUserName" => "acluser1",
                                "modifiedUserName" => "acluser1",
                                'dateCreated' => null,
                                'dateModified' => null,
                                'lastActivityDate' => null,
                                'description' => null,
                                'endDate' => null,
                                'startDate' => null,
                                'status' => null,
                                'statusId' => null,
                                'milestoneId' => null,
                                'parentId' => null,
                                'conversationRoomId' => null
                            )
                        ])
                    )
            ]
        ];
    }
}