<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\REST\Controllers;

/**
 * Components are used to extend the behaviors of controllers in a reusable way.
 * This is the parent class of the component and contains the default component behavior
 *
 * @class Component
 * @package Gaia\MVC\REST\Controllers\Components
 */
class Component
{
    /**
     * This is the initialize function which is called by default
     *
     * @return bool
     */
    public function initialize()
    {
        return true;
    }
}