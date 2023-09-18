<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Tests\Gaia\Acl;

/**
 * This is the base class for assignment models.
 * 
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
abstract class BaseAssignment extends \Tests\Gaia\Acl\BaseAcl
{
    /**
     * This method is used to setup some pre-conditions before the loading of any test case. In this method
     * we're setting up required models and data path for the test case and after that calling base class
     * setUpBeforeClass method for further exection of the test case.
     * 
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::$models = [
            "users" => array(
                'alias' => 'user',
                'namespace' => '\\Gaia\\MVC\\Models\\User',
                'createModel' => 'createUser',
                'behaviors' => array(
                    'aclBehavior'
                )
            ),
            "issues" => array(
                'alias' => 'issue',
                'namespace' => '\\Gaia\\MVC\\Models\\Issue',
                'behaviors' => array(
                    'aclBehavior'
                )
            ),
            "projects" => array(
                'alias' => 'project',
                'namespace' => '\\Gaia\\MVC\\Models\\Project',
                'behaviors' => array(
                    'aclBehavior'
                )
            ),
            "roles" => array(
                'alias' => 'role',
                'namespace' => '\\Gaia\\MVC\\Models\\Role',
                'behaviors' => array(
                    'aclBehavior'
                )
            ),
            "resources" => array(
                'alias' => 'resource',
                'namespace' => '\\Gaia\\MVC\\Models\\Resource',
                'createModel' => 'createResource',
                'deleteModel' => 'deleteResource',
                'behaviors' => array(
                    'aclBehavior'
                )
            ),
            "memberships" => array(
                'alias' => 'membership',
                'namespace' => '\\Gaia\\MVC\\Models\\Membership',
                'behaviors' => array(
                    'aclBehavior'
                )
            ),
            "permissions" => array(
                'alias' => 'permission',
                'namespace' => '\\Gaia\\MVC\\Models\\Permission',
                'behaviors' => array(
                    'aclBehavior'
                )
            ),
            "issuetypes" => array(
                'alias' => 'issuetype',
                'namespace' => "\\Gaia\\MVC\\Models\\Issuetype",
                'behaviors' => array(
                    'aclBehavior'
                )
            )
        ];

        self::$dataPath = APP_PATH . DS . 'tests' . DS . 'acl' . DS . 'access-level-' . self::$accessLevel . DS . self::$testType . DS . 'assignment' . DS . 'data';
        parent::setUpBeforeClass();
    }
}