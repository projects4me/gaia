<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

class aclComponent extends Component
{
    public function beforeList($controller)
    {

        return true;
    }
}
