<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\MVC\REST\Controllers\RestController;

/**
 * This controller is used to provide API interface for OAuth 2.- based
 * authentication.
 * 
 * 
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */ 
class TokenController extends RestController
{
    /**
     * This is the flag that removed Token Controller from authenication
     * check.
     * 
     * @var bool 
     */
    protected $authorization = false;
    
    /**
     * This function is used for the actual authentication. 
     * 
     * @return array
     */
    public function postAction() {
        // Populate data from the request
        $request = \OAuth2\Request::createFromGlobals();
        
        // Allow exceptions for the our application so as to make it easy for 
        // ember-data integration. For the rest the default implementation 
        // provided by bshaffer/oauth2-server-php
        $fromApplication = FALSE;
        if (isset($request->request['token']))
        {   
            $fromApplication = TRUE;
            $request->request = $request->request['token'];
        }
        
        // include OAuth2 Server object
        require_once APP_PATH.'/foundation/libs/oAuthServer.php';
        
        // Handle a request for an OAuth2.0 Access Token and send the response to the client
        $response = $server->handleTokenRequest($request);
        $this->response->setStatusCode($response->getStatusCode(), $response->getStatusText());
        
        // Create a wrapper in response for ember-data
        if ($fromApplication)
            $this->response->setJsonContent(array('token'=>$response->getParameters()));
        else
            $this->response->setJsonContent($response->getParameters());
            
        return $this->response;
    }
}
