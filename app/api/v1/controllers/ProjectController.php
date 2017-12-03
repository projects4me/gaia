<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

use Foundation\Mvc\RestController;

/**
 * Projects Controller
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProjectController extends RestController
{
    /*
    public function getAction()
    {
        $modelName = $this->modelName;

        //$data = $modelName::find($this->id);
        $Project = $modelName::findFirst($this->id);
        print "User Permission : ".(\Foundation\Acl::hasProjectAccess('1','Accounts', 'read', $this->id))."\r\n";

        $allTeams = $Project->getTeams();
        foreach ($allTeams as $key => $team) {
            print "Team $key: ".$team->name;
            print "<br>".$team->id;
            print "<hr>";
        }

        $allUsers = $Project->getUsers();
        foreach ($allUsers as $key => $user) {
            print "User $key: ".$user->username;
            print "<br>".$user->status;
            print "<hr>";
        }

        $allRoles = $Project->getRoles();
        foreach ($allRoles as $key => $role) {
            print "Role $key: ".$role->name;
            print "<br>".$role->id;
            print "Permission : ".(\Foundation\Acl::roleHasAccess($role->id,'Accounts','read'))." ||-";
            print "<hr>";
        }
        $userRoles = $Project->getRoles("userId = '1'");
        foreach ($userRoles as $key => $role) {
            print "Role $key: ".$role->name;
            print "<br>".$role->id;
            print "<hr>";
        }

        die();

        $modelName = $this->modelName;
        $notes = $modelName::find($this->id);
        return $this->extractData($notes);
    }*/
}
