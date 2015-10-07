<?php

use Foundation\Mvc\RestController;

class NotesController extends RestController
{
    
    public function getAction(){
        $modelName = $this->modelName;
        $notes = $modelName::find($this->id);
        return $this->extractData($notes);
    }
}
