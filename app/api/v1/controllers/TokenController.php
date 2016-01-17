<?php

use Foundation\Mvc\RestController;

class TokenController extends RestController
{
    protected $authorization = false;
    public function postAction() {
        $request = OAuth2\Request::createFromGlobals();
        $fromApplication = FALSE;
        if (isset($request->request['token']))
        {   
            $fromApplication = TRUE;
            $request->request = $request->request['token'];
        }
        // include our OAuth2 Server object
        require_once APP_PATH.'/foundation/libs/oAuthServer.php';
        // Handle a request for an OAuth2.0 Access Token and send the response to the client
        $response = $server->handleTokenRequest($request);
        $this->response->setStatusCode($response->getStatusCode(), $response->getStatusText());
        if ($fromApplication)
            $this->response->setJsonContent(array('token'=>$response->getParameters()));
        else
            $this->response->setJsonContent($response->getParameters());
            
        return $this->response;
/*        $response->getHttpHeaders;
        $this->response->
        print_r($response);
        die();
        $response->send();
*/
//        exit();
    }
}
