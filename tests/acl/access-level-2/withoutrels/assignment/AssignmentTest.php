<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Tests\Gaia\Acl\AccessLevel2\WithoutRels;

/**
 * This class is responsible to test the assignment model without relationships with access level of 2 on model.
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
        self::$accessLevel = '2';
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
                        ]
                    ]
                )
            ]
        ];
    }
}
