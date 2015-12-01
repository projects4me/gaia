<?php

use Foundation\Mvc\RestController;

class TeamsController extends RestController
{
    
    public function getAction()
    {
        $modelName = $this->modelName;

        //$data = $modelName::find($this->id);
        $Team = $modelName::findFirst($this->id);
        $allProjects = $Team->getProjects();
        foreach ($allProjects as $key => $project) {
            print "Note $key: ".$project->name;
            print "<br>".$project->id;
            print "<hr>";
        }
        
        $allUsers = $Team->getUsers();
        foreach ($allUsers as $key => $user) {
            print "User $key: ".$user->username;
            print "<br>".$user->status;
            print "<hr>";
        }

        die();
        
        $modelName = $this->modelName;
        $notes = $modelName::find($this->id);
        return $this->extractData($notes);
    }
}
