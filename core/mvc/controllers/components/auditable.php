<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

/**
 * This class is used to add auditing in the controller
 *
 * @class auditableComponent
 * @package Gaia\MVC\REST\Controllers\Components
 */
class auditableComponent extends Component
{
    /**
     * This function is called before a list is retrieved
     *
     * @param $controller
     * @return bool
     */
    public function beforeList($controller)
    {

        return true;
    }
}
