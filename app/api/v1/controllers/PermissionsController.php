<?php

use Foundation\Mvc\RestController;

class PermissionsController extends RestController
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
