<?php

// Test fixture for tests/tools/gaia-dev: the part of RestController the extractor reads.
namespace Gaia\Core\MVC\REST\Controllers;

class RestController
{
    protected $aclMap = array(
        'get' => array('action' => 'get', 'controllerAction' => 'getAction'),
        'post' => array('action' => 'create', 'controllerAction' => 'postAction'),
    );
}
