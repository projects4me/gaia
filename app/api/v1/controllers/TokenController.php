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
        $request = $this->getOAuthRequest();
        $grantType = $request->request('grant_type');

        // Allow exceptions for the our application so as to make it easy for
        // ember-data integration. For the rest the default implementation
        // provided by bshaffer/oauth2-server-php
        $fromApplication = false;
        if (isset($request->request['token'])) {
            $fromApplication = true;
            $request->request = $request->request['token'];
        }

        $oauthServer = $this->getOAuthServer($request);
        $oauthServer->addGrantType();

        // Check failed login attempts before handling the OAuth2.0 Access Token request
        if ($grantType === 'password') {
            $this->handleFailedLoginAttempt($request);
        }

        // Handle a request for an OAuth2.0 Access Token and send the response to the client
        $response = $oauthServer->getServer()->handleTokenRequest($request);

        if ($grantType === 'password') {
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
            $user = $this->getUserByUsername($request->request['username']);

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

    /**
     * Get the user by username.
     *
     * @param  string $username
     * @return \Gaia\MVC\Models\User
     */
    protected function getUserByUsername($username)
    {
        $user = \Gaia\MVC\Models\User::findFirstByUsername($username);
        if (!$user) {
            throw new Exception("User not found.");
        }
        return $user;
    }

    /**
     * Handle failed login attempts for OAuth2 authentication.
     *
     * @param  \OAuth2\Request $request
     * @throws Exception
     */
    private function handleFailedLoginAttempt($request)
    {
        $username = $request->request('username');
        $user = $this->getUserByUsername($username);
        $oauthConfig = $this->config->get('oauth');
        $failedLimit = $oauthConfig['failedLoginAttemptsLimit'];

        $lastFailedAttempt =  $user->lastFailedAttempt ?? gmdate('Y-m-d H:i:s');
        $lockTime = strtotime($lastFailedAttempt);
        $currentTime = time();
        $timeDiff = $currentTime - $lockTime;
        $lockTimeInHours = $oauthConfig['failedLoginLockTime'];
        $lockTimeInSeconds = $lockTimeInHours * 60 * 60; // Convert lock time to seconds

        if (!password_verify($request->request('password'), $user->password)) {
            // Check if time difference is greater than 24 hours (default), if yes then reset the failed login attempts
            if ($timeDiff > $lockTimeInSeconds) {
                $this->resetUserFailedAttempts($user);
            } elseif ($user->failedLoginAttempts < $failedLimit) {
                $this->resetUserFailedAttempts($user, $user->failedLoginAttempts + 1);
            } else {
                throw new \Gaia\Exception\ResourceLocked("User is locked. Please try again later.");
            }
        }
        // If the password is correct, and lock time is expired then reset the failed login attempts
        elseif ($timeDiff > $lockTimeInSeconds) {
            $this->resetUserFailedAttempts($user);
        } elseif ($user->failedLoginAttempts >= $failedLimit) {
            throw new \Gaia\Exception\ResourceLocked("User is locked. Please try again later.");
        }
    }

    /**
     * Reset the failed login attempts for the user either to 0 or to the given limit.
     *
     * @param \Gaia\MVC\Models\User $user
     * @param int                   $loginAttempts
     */
    private function resetUserFailedAttempts(\Gaia\MVC\Models\User $user, $loginAttempts = 0)
    {
        $this->setCurrentUser($user);
        $user->failedLoginAttempts = $loginAttempts;
        $user->lastFailedAttempt = gmdate('Y-m-d H:i:s');
        $user->save();
    }
}
