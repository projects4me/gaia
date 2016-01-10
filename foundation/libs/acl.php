<?php

/* 
 * Projects4Me Community Edition is an open source project management software 
 * developed by PROJECTS4ME Inc. Copyright (C) 2015-2016 PROJECTS4ME Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 (GNU AGPL v3) as
 * published be the Free Software Foundation with the addition of the following 
 * permission added to Section 15 as permitted in Section 7(a): FOR ANY PART OF 
 * THE COVERED WORK IN WHICH THE COPYRIGHT IS OWNED BY PROJECTS4ME Inc., 
 * Projects4Me DISCLAIMS THE WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU AGPL v3 for more details.
 * 
 * You should have received a copy of the GNU AGPL v3 along with this program; 
 * if not, see http://www.gnu.org/licenses or write to the Free Software 
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 * You can contact PROJECTS4ME, Inc. at email address contact@projects4.me.
 * 
 * The interactive user interfaces in modified source and object code versions 
 * of this program must display Appropriate Legal Notices, as required under 
 * Section 5 of the GNU AGPL v3.
 * 
 * In accordance with Section 7(b) of the GNU AGPL v3, these Appropriate Legal 
 * Notices must retain the display of the "Powered by Projects4Me" logo. If the 
 * display of the logo is not reasonably feasible for technical reasons, the 
 * Appropriate Legal Notices must display the words "Powered by Projects4Me".
 */

namespace Foundation;

use Phalcon\Mvc\Model\Query as Query;

/**
 * @todo Fill in Documentation and fix namespace
 */
class Acl{
    
    /**
     * Check is the user has access to a particular resrouce within the domain
     * of a certain project
     * 
     * Also allowing the possibilty of having one user associated with one
     * project with multiple roles
     * 
     * @param string $userId
     * @param string $resource
     * @param string $control
     * @param string $projectId
     * @return int
     */
    public static function hasProjectAccess($userId,$resource,$control,$projectId)
    {
        $access = 0;
        $max = $access;
        
        if (!(is_null($projectId) || empty($projectId) || $projectId === 0))
        {
            if (isset($userId) && !empty($userId))
            {
                $ProjectsRoles = \ProjectsRoles::find(array("projectId = '".$projectId."' AND userId='".$userId."'"));
                foreach($ProjectsRoles as $ProjectRole)
                {
                    $permission = self::roleHasAccess($ProjectRole->roleId, $resource, $control);
                    if ($permission > $max)
                    {
                        $max = $permission;
                    }
                }
                $access = $max;
            }
        }
       return $access;
    }
    
    /**
     * Get all the projects that the user has access to
     * 
     * @param type $userId
     * @param type $resource
     * @param type $control
     * @return type
     */
    public static function getProjects($userId,$resource,$control)
    {
        $projects = array();
        
        if (isset($userId) && !empty($userId))
        {
            $ProjectsRoles = \ProjectsRoles::find(array("userId='".$userId."'"));
            foreach($ProjectsRoles as $ProjectRole)
            {
                $permission = self::roleHasAccess($ProjectRole->roleId, $resource, $control);
                if ($permission > 0)
                {
                    $projects[] = $ProjectRole->projectId;
                }
            }
        }
        return $projects;
    }
    
    /**
     * check if a role has access to a particular control
     * 
     * @param type $roleId
     * @param type $resource
     * @param type $control
     * @return type
     */
    public static function roleHasAccess($roleId,$resource,$control='read')
    {
        $access = 0;
        if (isset($roleId) && !empty($roleId))
        {
            $Resource = \Resources::find(array("entity='".$resource."'"));

            // If the resrouce exists then check for the permissions
            if (isset($Resource[0]))
            {
                $controlField = '_'.$control;
                
                // First check out the permissions for the resource directly
                $Permission = \Permissions::findFirst(array("resourceId='".($Resource[0]->id)."' AND roleId='".$roleId."'"));
                if (!isset($Permission->id) && isset($Resource[0]->parentId))
                {   
                    // if not found then check for its parent
                    $Permission = \Permissions::findFirst(array("resourceId='".($Resource[0]->parentId)."' AND roleId='".$roleId."'"));
                }
                
                if (isset($Permission->id))
                {
                    $permission = (int) $Permission->$controlField;
                    if ($permission != 0)
                    {
                        $access = $permission;
                    }
                }
            }
        }
        return $access;
    }
}

