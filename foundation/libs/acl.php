<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
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
                $ProjectsRoles = \Gaia\MVC\Models\Membership::find(array("projectId = '".$projectId."' AND userId='".$userId."'"));
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
            $ProjectsRoles = \Gaia\MVC\Models\Membership::find(array("userId='".$userId."'"));
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
            $Resource = new \Gaia\MVC\Models\Resource();
            $Resources = $Resource->getResource($resource);

            // If the resrouce exists then check for the permissions
            foreach ($Resources as $ResourceRow)
            {
                $controlField = '_'.$control;

                // First check out the permissions for the resource directly
                $Permission = \Gaia\MVC\Models\Permission::findFirst(array("resourceId='".($ResourceRow->id)."' AND roleId='".$roleId."'"));
                if (isset($Permission->id))
                {
                    $permission = (int) $Permission->$controlField;
                    if ($permission != 0)
                    {
                        $access = $permission;
                        break;
                    }
                }
            }
        }
        return $access;
    }
}
