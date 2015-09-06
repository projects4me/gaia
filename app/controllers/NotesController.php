<?php

use Foundation\Mvc\RestController;

class NotesController extends RestController
{
    public function getAction(){
        $modelName = $this->modelName;

        $notes = $modelName::findFirst( $this->id );
        $user = $notes->getAssignedUser();
        print_r($user->username);
        
        die();
        return $this->extractData($notes);
    }
}
