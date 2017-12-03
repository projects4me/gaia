<?php
/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Test;

require dirname(__FILE__).'/TestHelper.php';
require dirname(__FILE__).'/UnitTestCase.php';

/**
 * Class UnitTest
 */
class UnitTest extends \UnitTestCase
{
    public function testTestCase()
    {
        $this->assertEquals('works',
            'works',
            'This is OK'
        );

        $this->assertEquals('works',
            'works1',
            'This will fail'
        );
    }
}
