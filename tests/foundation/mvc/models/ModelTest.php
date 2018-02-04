<?php
/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Tests;

use Gaia\MVC\Models\Model;

/**
 * Class ModelTest
 */
class ModelTest extends \UnitTestCase
{
    /**
     * @param $statement
     * @param $query
     * @dataProvider preProcessWhereProvider
     */
    public function testPreProcessWhere($statement,$query)
    {
        $this->assertEquals($query,Model::preProcessWhere($statement));

    }

    public function preProcessWhereProvider()
    {
        return array(
            array('(Issue.id : 1)', "(Issue.id = '1')"),
            array('(Issue.id !: 1)', "(Issue.id != '1')")
        );
    }
}
