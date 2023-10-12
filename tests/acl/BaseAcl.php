<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Tests\Gaia\Acl;

use PHPUnit\Framework\TestCase,
Phalcon\DI,
Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * This is the base class that contains all of the ACL related implementation and required test cases.
 * 
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
abstract class BaseAcl extends TestCase
{
    /**
     * This contains array of user model.
     * 
     * @var array
     */
    protected static $users = [];

    /**
     * This contains array of membership model.
     * 
     * @var array
     */
    protected static $memberships = [];

    /**
     * This contains array of role model.
     * 
     * @var array
     */
    protected static $roles = [];

    /**
     * This contains array of resource model.
     * 
     * @var array
     */
    protected static $resources = [];

    /**
     * Prefix that will be attached with the resource name.
     * 
     * @var string
     */
    protected static $resourcePrefix = 'Test';

    /**
     * This contains array of permission model.
     * 
     * @var array
     */
    protected static $permissions = [];

    /**
     * This is the access level of resource.
     * 
     * @var string
     */
    protected static $accessLevel = null;

    /**
     * This contains array of models that will be created for testing.
     * 
     * @var array
     */
    protected static $models = [];

    /**
     * This contains array of project model.
     * 
     * @var array
     */
    protected static $projects = [];

    /**
     * This contains array of issue model.
     * 
     * @var array
     */
    protected static $issues = [];

    /**
     * This contains array of the data path (csv) of each model.
     * 
     * @var array
     */
    protected static $dataPath = [];

    /**
     * This is the test type to be executed.
     * 
     * @var array
     */
    protected static $testType;

    /**
     * This contains array of issuetype model.
     * 
     * @var array
     */
    protected static $issuetypes = [];

    /**
     * This method is used to setup some pre-conditions before the loading of any test case.
     * 
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        self::setUpData();
    }

    /**
     * This method loads data required for testing.
     * 
     */
    public static function setUpData()
    {
        foreach (self::$models as $key => $model) {
            $alias = $model['alias'];
            $modelNamespace = $model['namespace'];

            $path = self::$dataPath . DS . "$alias.csv";

            $fileContents = @file($path) ?: [];
            $data = array_map('str_getcsv', $fileContents);
            $count = count($data ?? []);
            for ($i = 1; $i < $count; $i++) {
                $values = array_combine($data[0], $data[$i]);
                $newModel = null;

                if (!isset($model['createModel'])) {
                    $nullableAttributes = isset($model['nullableAttributes']) ? $model['nullableAttributes'] : null;
                    $newModel = self::createModel($modelNamespace, $model['behaviors'], $values, $nullableAttributes);
                }
                else {
                    $newModel = self::{ $model['createModel']}($modelNamespace, $model['behaviors'], $values);
                }

                self::$$key[] = $newModel;
            }
        }
    }

    /**
     * This method is used to create model.
     * 
     * @param string $modelNamespace
     * @param array $behaviors
     * @param array $values
     * @return \Gaia\Core\MVC\Models\Model
     */
    public static function createModel($modelNamespace, $behaviors, $values, $nullableAttributes = null)
    {
        $model = self::createModelReflection($modelNamespace, $behaviors);
        $newModel = $model['model'];
        $instance = $model['instance'];

        /**
         * Attributes on which empty values are allowed. This is done because for some cases we need to add empty
         * values against an attribute which cannot accept null values. And for that case Phalcon doesn't insert that
         * model and gives us an error.
         */
        if ($nullableAttributes) {
            $allowEmptyStringValues = $newModel->getMethod('allowEmptyStringValues');
            $allowEmptyStringValues->setAccessible(true);
            $allowEmptyStringValues->invoke($instance, $nullableAttributes);
        }

        //assign and save model
        $newModel->getMethod('assign')->invoke($instance, $values);
        $newModel->getMethod('save')->invoke($instance);

        return $instance;
    }

    public static function createModelReflection($modelNamespace, $behaviors)
    {
        //create reflection class to add required behaviors before the creation of object.
        $newModel = new \ReflectionClass($modelNamespace);
        $instance = $newModel->newInstanceWithoutConstructor();

        //add required behaviors property
        $reflectedBehaviorProperty = $newModel->getProperty('requiredBehaviors');
        $reflectedBehaviorProperty->setAccessible(true);
        $reflectedBehaviorProperty->setValue($instance, $behaviors);

        //invoke construct method of model
        $method = $newModel->getMethod('__construct');
        $method->invoke($instance);

        return array(
            'model' => $newModel,
            'instance' => $instance
        );
    }

    /**
     * @dataProvider data
     * 
     * This method is the test case which is used to get the required resource and assert it with the expected one (data provider).
     * 
     * @param array $params
     * @param string $resource
     * @param string $modelName
     * @param string $modelNamespace
     * @param array $expected
     */
    public function testGetResource($params, $resource, $modelName, $modelNamespace, $expected)
    {
        $restController = new RestController();
        $getRelsReflection = new \ReflectionMethod('Gaia\\Core\\MVC\\REST\\Controllers\\RestController', 'getRelsWithMeta');
        $getRelsReflection->setAccessible(true);

        $permission = new \Gaia\MVC\Models\Permission();
        $permission->setResourcePrefix(self::$resourcePrefix);
        $permission->fetchUserPermissions(self::$users[0]->id, '_read');
        $permission->checkAccess($resource, $modelName);

        //call getRelsWithMeta function of Rest Controller
        $rels = ($params['rels']) ? implode(",", $params['rels']) : null;
        $relationships = $getRelsReflection->invoke($restController, $rels, $modelName);
        $permission->checkRelsAccess($relationships, 'Read');

        //modifiy method access
        $reflectionMethod = new \ReflectionMethod('Gaia\\Core\\MVC\\REST\\Controllers\\RestController', 'extractData');
        $reflectionMethod->setAccessible(true);

        //modify property access
        $reflectionProperty = new \ReflectionProperty('Gaia\\Core\\MVC\\REST\\Controllers\\RestController', 'modelName');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($restController, $modelName);

        $di = DI::getDefault();

        $di->set(
            'permission',
            $permission
        );

        $model = new $modelNamespace();
        $data = $model->readAll($params);
        $dataArray = $reflectionMethod->invoke($restController, $data);
        $this->assertEquals($expected, $dataArray);
    }

    /**
     * This method is called once after all test methods are executed in the class.
     */
    public static function tearDownAfterClass(): void
    {
        foreach (self::$models as $key => $modelData) {
            $deleteFunction = (!isset($modelData['deleteModel'])) ? false : $modelData['deleteModel'];

            foreach (self::$$key as $model) {
                ($deleteFunction) ?self::{ $deleteFunction}($model) : $model->delete();
            }

            self::$$key = [];
        }
    }

    /**
     * This method is used to create user model.
     * 
     * @param string $modelNamespace
     * @param array $values
     * @return \Gaia\MVC\Models\User
     */
    public static function createUser($modelNamespace, $behaviors, $values)
    {
        global $currentUser;

        $model = self::createModelReflection($modelNamespace, $behaviors);
        $newModel = $model['model'];
        $instance = $model['instance'];

        //assign and save model
        $newModel->getMethod('assign')->invoke($instance, $values);
        $currentUser = $instance;
        $newModel->getMethod('save')->invoke($instance);

        return $instance;
    }

    /**
     * This method is used to create resource model.
     * 
     * @return \Gaia\MVC\Models\Resource
     */
    public static function createResource($modelNamespace, $behaviors, $values)
    {
        $model = self::createModelReflection($modelNamespace, $behaviors);
        $newModel = $model['model'];
        $instance = $model['instance'];

        $parentNode = $newModel->getMethod('findFirst')->invoke($instance, "entity='App'");

        if ($parentNode) {
            $parentRHT = $parentNode->rht;

            $updateLFTPhql = "UPDATE resources set lft = lft+2 where lft>=$parentRHT";
            $updateRHTPhql = "UPDATE resources set rht = rht+2 where rht>=$parentRHT";

            \Phalcon\Di::getDefault()->get('db')->query($updateLFTPhql);
            \Phalcon\Di::getDefault()->get('db')->query($updateRHTPhql);

            $values['lft'] = $parentRHT;
            $values['rht'] = $parentRHT + 1;
            $values['parentId'] = $parentNode->id;
        }

        $newModel->getMethod('assign')->invoke($instance, $values);
        $newModel->getMethod('save')->invoke($instance);

        return $instance;
    }

    /**
     * This method is used to delete resource model.
     * 
     * @param \Gaia\MVC\Models\Resource $resource
     */
    public static function deleteResource($resource)
    {
        $model = self::createModelReflection('\\Gaia\\MVC\\Models\\Resource', array());
        $newModel = $model['model'];
        $instance = $model['instance'];

        $node = $newModel->getMethod('findFirst')->invoke($instance, "entity='$resource->entity'");

        $nodeRHT = $node->rht;
        $nodeLFT = $node->lft;

        $rangeWidth = ($nodeRHT - $nodeLFT) + 1;
        $updateLFTPhql = "UPDATE resources set lft = lft-$rangeWidth where lft>=$nodeRHT";
        $updateRHTPhql = "UPDATE resources set rht = rht-$rangeWidth where rht>=$nodeRHT";

        \Phalcon\Di::getDefault()->get('db')->query($updateLFTPhql);
        \Phalcon\Di::getDefault()->get('db')->query($updateRHTPhql);

        $node->delete();
    }
}