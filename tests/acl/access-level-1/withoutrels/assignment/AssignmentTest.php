<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Tests\Gaia\Acl\AccessLevel1\WithoutRels;

/**
 * This class is responsible to test the assignment model without relationships with access level of 1 on model.
 * 
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AssignmentTest extends \Tests\Gaia\Acl\BaseAssignment
{
    /**
     * This method is used to setup some pre-conditions before the loading of any test case.
     * 
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::$accessLevel = '1';
        self::$testType = 'withoutrels';
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
                    "rels" => array(),
                    "where" => "((Issue.projectId : p-acl-1))",
                    "sort" => "CAST(id as UNSIGNED)",
                    "order" => "DESC",
                    "limit" => 21,
                    "offset" => 0,
                    "groupBy" => array(),
                    "count" => "",
                    "having" => "",
                    "addRelFields" => false
                ],
                'Issue.Read',
                'Issue',
                '\\Gaia\\MVC\\Models\\Issue',
                    array(
                    'data' => [
                        [
                            "type" => "Issue",
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
                            "type" => "Issue",
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
                        ],
                        [
                            "type" => "Issue",
                            "id" => "i-acl-3",
                            "attributes" => array(
                                "subject" => "acl-issue-3",
                                "deleted" => "0",
                                "createdUser" => "u-acl-1",
                                'owner' => 'u-acl-1',
                                'assignee' => 'u-acl-1',
                                'reportedUser' => 'u-acl-1',
                                "modifiedUser" => "u-acl-1",
                                'issueNumber' => '100002',
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
                            "type" => "Issue",
                            "id" => "i-acl-4",
                            "attributes" => array(
                                "subject" => "acl-issue-4",
                                "deleted" => "0",
                                "createdUser" => "u-acl-1",
                                'owner' => 'u-acl-1',
                                'assignee' => 'u-acl-1',
                                'reportedUser' => 'u-acl-1',
                                "modifiedUser" => "u-acl-1",
                                'issueNumber' => '100003',
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
                    ]
                )
            ]
        ];
    }
}
