<?php

use Foundation\Mvc\RestController;

class ResourcesController extends RestController
{
    /**
     * Project authorization flag
     * @var bool
     */
    protected $projectAuthorization = false;
    
    /**
     * System level flag
     * @var bool
     */
    protected $systemLevel = true;
}