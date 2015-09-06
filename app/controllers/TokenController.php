<?php

use Foundation\Mvc\RestController;

class TokenController extends RestController
{
    
    public function saveAction() {
        // include our OAuth2 Server object
        require_once APP_PATH.'/foundation/libs/oAuthServer.php';

        // Handle a request for an OAuth2.0 Access Token and send the response to the client
        $server->handleTokenRequest(OAuth2\Request::createFromGlobals())->send();        
    }
}
