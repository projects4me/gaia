<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * This controller is used to provide API interface for OAuth 2.- based
 * authentication.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
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
    public function postAction()
    {
        // Populate data from the request
        $request = \OAuth2\Request::createFromGlobals();

        // Allow exceptions for the our application so as to make it easy for
        // ember-data integration. For the rest the default implementation
        // provided by bshaffer/oauth2-server-php
        $fromApplication = false;
        if (isset($request->request['token'])) {
            $fromApplication = true;
            $request->request = $request->request['token'];
        }

        // include OAuth2 Server object
        include_once APP_PATH.'/core/libs/authorization/oAuthServer.php';

        // Handle a request for an OAuth2.0 Access Token and send the response to the client
        $response = $server->handleTokenRequest($request);

        if ($request->request['grant_type'] === 'password') {
            $this->handlePasswordGrant($request, $response);
        }

        $this->response->setStatusCode($response->getStatusCode(), $response->getStatusText());

        // Create a wrapper in response for ember-data
        if ($fromApplication) {
            $this->response->setJsonContent(array('token' => $response->getParameters()));
        } else {
            $this->response->setJsonContent($response->getParameters());
        }

        return $this->response;
    }

    /**
     * Handle password grant type for OAuth2 authentication.
     *
     * @param  \OAuth2\Request  $request
     * @param  \OAuth2\Response $response
     * @throws Exception
     */
    private function handlePasswordGrant($request, $response)
    {
        $accessToken = $response->getParameter('access_token');

        if ($accessToken) {
            $oauthConfig = $this->config->get('oauth');
            $this->validateOAuthConfig($oauthConfig);

            // Get the user by username
            $username = $request->request['username'];
            $user = \Gaia\MVC\Models\User::findFirstByUsername($username);

            if (!$user) {
                throw new Exception("User not found.");
            }

            // Set current user to avoid behavior-related issues
            $this->setCurrentUser($user);

            // Set session and remember me expiration times
            $this->setSessionExpiration($user, $oauthConfig);
            $this->setRememberMeExpiration($user, $request, $oauthConfig);
            $user->save();
        }
    }

    /**
     * Validate the OAuth configuration values.
     *
     * @param  array $oauthConfig
     * @throws Exception
     */
    private function validateOAuthConfig(\Phalcon\Config $oauthConfig)
    {
        if (!is_numeric($oauthConfig['rememberMeTimeout']) || !is_numeric($oauthConfig['sessionTimeout'])) {
            throw new Exception("Session/Remember me Timeout should be a number");
        }
    }

    /**
     * Set session expiration for the user.
     *
     * @param \Gaia\MVC\Models\User $user
     * @param array                 $oauthConfig
     */
    private function setSessionExpiration(\Gaia\MVC\Models\User $user, \Phalcon\Config $oauthConfig)
    {
        $sessionTimeout = $oauthConfig['sessionTimeout'];
        $sessionExpires = gmdate('Y-m-d H:i:s', strtotime("+$sessionTimeout hours"));
        $user->sessionExpires = $sessionExpires;
    }

    /**
     * Set "Remember Me" expiration for the user.
     *
     * @param \Gaia\MVC\Models\User $user
     * @param \OAuth2\Request       $request
     * @param array                 $oauthConfig
     */
    private function setRememberMeExpiration(\Gaia\MVC\Models\User $user, $request, \Phalcon\Config $oauthConfig)
    {
        $rememberMeTimeout = $oauthConfig['rememberMeTimeout'];
        $rememberMe = filter_var($request->request('remember_me'), FILTER_VALIDATE_BOOLEAN);

        if ($rememberMe) {
            $user->rememberMe = gmdate('Y-m-d H:i:s', strtotime("+$rememberMeTimeout hours"));
        } else {
            $user->rememberMe = gmdate('Y-m-d H:i:s', strtotime('-1 day'));
        }
    }

    /**
     * Set the current user globally or in the session.
     *
     * @param \Gaia\MVC\Models\User $user
     */
    private function setCurrentUser(\Gaia\MVC\Models\User $user)
    {
        // Assuming the `$currentUser` needs to be set for other parts of the application
        global $currentUser;
        $currentUser = $user;
    }
}
