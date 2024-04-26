<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Tests\Gaia\Acl\AccessLevel2\WithoutRels;

/**
 * This class is responsible to test the group model without relationships with access level of 2 on model.
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
                            )
                        ],
                        [
                            "type" => "Project",
                            "id" => "p-acl-2",
                            "attributes" => array(
                                "name" => "test-project-2",
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
                                "shortCode" => "pac2",
                                "status" => "in_progress",
                                "estimatedBudget" => null,
                                "spentBudget" => null,
                                "type" => "software",
                                "scope" => null,
                                "vision" => null,
                                "done" => "0"
                            )
                        ],
                    ]
                    )
            ]
        ];
    }
}
