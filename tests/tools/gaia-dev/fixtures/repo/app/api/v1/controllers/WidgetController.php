<?php

// Test fixture for tests/tools/gaia-dev: a REST controller with components.
namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

class WidgetController extends RestController
{
    public $uses = array('Notify', 'Events\\WidgetLiveEvents');

    protected function helper()
    {
    }
}
