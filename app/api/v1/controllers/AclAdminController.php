<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * AclAdmin Controller reponsible for checking whether autheticated user has admin rights on a particular resource/controller's action or not.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
abstract class AclAdminController extends RestController
{
    /**
     * The value of admin access level. If user has this value then admin rights will be the given to user.
     * 
     * @var string
     */
    private $adminAccessLevel = '8';

    /**
     * This flag is used to check whether there is need to call the RestController's action or not.
     * 
     * @var bool
     */
    protected $callParent = true;

    /**
     * This method first checks user admin level access. If user has admin rights then the RestController's 
     * getAction() is called (if required).
     * 
     * @return \Phalcon\Http\Response|null
     */
    public function getAction()
    {
        $this->checkAdminAccess();
        return $this->callParent ?parent::getAction() : null;
    }

    /**
     * This method first checks user admin level access. If user has admin rights then the RestController's 
     * relatedAction() is called (if required).
     * 
     * @return \Phalcon\Http\Response|null
     */
    public function relatedAction()
    {
        $this->checkAdminAccess();
        return $this->callParent ?parent::relatedAction() : null;
    }

    /**
     * This method first checks user admin level access. If user has admin rights then the RestController's 
     * patchAction() is called (if required).
     * 
     * @return \Phalcon\Http\Response|null
     */
    public function patchAction()
    {
        $this->checkAdminAccess();
        return $this->callParent ?parent::patchAction() : null;
    }

    /**
     * This method first checks user admin level access. If user has admin rights then the RestController's 
     * postAction() is called (if required).
     * 
     * @return \Phalcon\Http\Response|null
     */
    public function postAction()
    {
        $this->checkAdminAccess();
        return $this->callParent ?parent::postAction() : null;
    }

    /**
     * This function is used to check the user access on the controller's action. If the controller/resource access level is 8, which is
     * admin access, then admin rights will be to the user and user is allowed to call that particular action.
     * 
     * @method checkAdminAccess
     */
    private function checkAdminAccess()
    {
        $controllerName = ucwords($this->controllerName);
        $permission = $this->getDI()->get('permission');
        $accessLevel = $permission->getAccess($controllerName);

        if ($accessLevel !== $this->adminAccessLevel)
            throw new \Gaia\Exception\Access("Access Denied");
    }
}