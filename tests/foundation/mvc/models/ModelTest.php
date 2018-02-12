<?php
/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Tests\Gaia\MVC\Models;

use Gaia\MVC\Models\Model as Model;
use Gaia\Libraries\Meta\Manager as metaManager;
use Phalcon;
use Phalcon\Mvc\Model\Manager as ModelsManager;

/**
 * Class ModelTest
 */
class ModelTest extends \UnitTestCase
{

    /**
     * This function is used to setup a mocked model
     *
     * @param Phalcon\DiInterface|null $di
     * @param Phalcon\Config|null $config
     */
//    public function setUp(\Phalcon\DiInterface $di = null, \Phalcon\Config $config = null)
//    {
//        $di->set(
//            "modelsManager",
//            function() {
//                return new ModelsManager();
//            }
//        );
//        parent::setUp($di, $config);
//    }

    /**
     * @param $statement
     * @param $query
     * @dataProvider preProcessWhereProviderSuccess
     */
    public function testPreProcessWhereSuccess($statement,$query)
    {
        $this->assertEquals($query,Model::preProcessWhere($statement));
    }

    public function preProcessWhereProviderSuccess()
    {
        return array(
            array('(Issue.id : 1)', "(Issue.id = '1')"),
            array('(Issue.id !: 1)', "(Issue.id != '1')"),
            array('(Issue.id > 1)', "(Issue.id > '1')"),
            array('(Issue.id < 1)', "(Issue.id < '1')"),
            array('(Issue.id >: 1)', "(Issue.id >= '1')"),
            array('(Issue.id <: 1)', "(Issue.id <= '1')"),
            array('(Issue.id CONTAINS 1)', "(Issue.id LIKE '%1%')"),
            array('(Issue.id CONTAINS \'1\',\'2\')', "(Issue.id IN ('1','2'))"),
            array('(Issue.id STARTS 1)', "(Issue.id LIKE '1%')"),
            array('(Issue.id ENDS 1)', "(Issue.id LIKE '%1')"),
            array('(Issue.id !CONTAINS 1)', "(Issue.id NOT LIKE '%1%')"),
            array('(Issue.id !CONTAINS \'1\',\'2\')', "(Issue.id NOT IN ('1','2'))"),
            array('(Issue.id !STARTS 1)', "(Issue.id NOT LIKE '1%')"),
            array('(Issue.id !ENDS 1)', "(Issue.id NOT LIKE '%1')"),
            array('(Issue.id NULL)', "(Issue.id IS NULL)"),
            array('(Issue.id !NULL)', "(Issue.id IS NOT NULL)"),
            array('(Issue.id EMPTY)', "(Issue.id = '')"),
            array('(Issue.id !EMPTY)', "(Issue.id != '')"),
            array('(Issue.id BETWEEN \'1\' AND \'2\')', "(Issue.id BETWEEN '1' AND '2')"),
            array('(Issue.id !BETWEEN \'1\' AND \'2\')', "(!(Issue.id BETWEEN '1' AND '2'))"),
        );
    }

    /**
     * @param $statement
     * @param $exception
     * @dataProvider preProcessWhereProviderException
     */
    public function testPreProcessWhereException($statement,$exception)
    {

        $this->expectExceptionMessage($exception);
        Model::preProcessWhere($statement);
    }

    public function preProcessWhereProviderException()
    {
        return array(
            array('((Issue.id !: 1)', 'Invalid query, please refer to the guides. Please check the parenthesis in the query. You have forgotten ")"'),
            array('(Issue.id !: 1))', 'Invalid query, please refer to the guides. Please check the parenthesis in the query. You have forgotten "("'),
            array('(Issue.id SOMETHING 1)', 'Invalid query, please check the guides. Most common issues are extra spaces and invalid operators, please note that "=" is not allowed use ":" instead. Possible issue in (Issue.id SOMETHING 1)'),
            array('Issue.id : 1', 'Invalid query, please refer to guides. Query must have at least one sub-statement enclosed in parenthesis.')
        );
    }

    public function testLoadBehavior(){
        $this->loadModel();


    }

    protected function loadModel()
    {
        $model = $this->createMock(Model::class);
        $model->method('getModelName')
            ->willReturn('Sample');

        $meta = array
        (
            'tableName' => 'sample',
            0 => array(
                0 => 'access_token',
                1 => 'client_id',
                2 => 'user_id',
                3 => 'expires',
                4 => 'scope'
            ),
            1 => array(
                0 => 'access_token'
            ),
            2 => array(
                0 => 'client_id',
                1 => 'user_id',
                2 => 'expires',
                3 => 'scope'
            ),
            3 => array(
                0 => 'access_token',
                1 => 'client_id',
            ),
            4 => array(
                'access_token' => 2,
                'client_id' => 2,
                'user_id' => 2,
                'expires' => 4,
                'scope' => 2
            ),
            5 => array(
            ),
            8 => 'access_token',
            9 => array(
                'access_token' => 2,
                'client_id' => 2,
                'user_id' => 2,
                'expires' => 2,
                'scope' => 2
            ),
            10 => array(
            ),
            11 => array(
            ),
            12 => array(
            ),
            13 => array(
            ),
            'relationships' => array(
            ),
            'behaviors' => array(
            )
        );

        $metaManger = $this->createMock(\Gaia\Libraries\Meta\Manager::class);

        $metaManger->method('getModelMeta')
            ->willReturn($meta);

        $f = $model->getFields();
        print_r($f);

    }

}
