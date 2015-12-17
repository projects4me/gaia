<?php

use Foundation\Mvc\RestController;

class ContactsController extends RestController
{
    /*
    public function getAction(){
        $modelName = $this->modelName;

        $data = $modelName::find($this->id);
        $Notes = $modelName::findFirst($this->id);
        $allNotes = $Notes->getNotes();
        foreach ($allNotes as $key => $note) {
            print "Note $key: ".$note->subject;
            print "<br>".$note->body;
            print "<hr>";
        }

        $allUsers = $Notes->getcontactsUsers();
        foreach ($allUsers as $key => $user) {
            print "User $key: ".$user->users->username;
            print "<br>".$user->users->status;
            print "<hr>";
        }

        $allUsers = $Notes->getusersContacts();
        foreach ($allUsers as $key => $user) {
            print "User $key: ".$user->username;
            print "<br>".$user->status;
            print "<hr>";
        }



//        print_r($allNotes);
        
        die();
        return $this->extractData($data);
    }*/
}
