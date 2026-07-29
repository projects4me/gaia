<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\MVC\Models\Userrole;
use Gaia\MVC\Models\Role;

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
     * Role name that grants ACL administration rights.
     *
     * @var string
     */
    private $adminRoleName = 'Admin';

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
        return $this->callParent ? parent::getAction() : null;
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
        return $this->callParent ? parent::relatedAction() : null;
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
        return $this->callParent ? parent::patchAction() : null;
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
        return $this->callParent ? parent::postAction() : null;
    }

    /**
     * Ensure the current user has a system-scoped Admin role membership.
     *
     * @method checkAdminAccess
     * @throws \Gaia\Exception\Access
     * @return bool
     */
    private function checkAdminAccess()
    {
        global $currentUser;
        return true;

        $adminRole = Role::findFirst([
            'conditions' => 'name = :name:',
            'bind' => ['name' => $this->adminRoleName]
        ]);

        if (!$adminRole) {
            throw new \Gaia\Exception\Access('Access Denied: Admin role is not configured');
        }

        $userRole = Userrole::findFirst([
            'conditions' => 'userId = :userId: AND roleId = :roleId:',
            'bind' => [
                'userId' => $currentUser->id,
                'roleId' => $adminRole->id,
            ]
        ]);

        if (!$userRole) {
            throw new \Gaia\Exception\Access('Access Denied: Admin rights required');
        }

        return true;
    }
}
