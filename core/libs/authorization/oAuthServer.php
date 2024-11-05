<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * This is the oAuthServer code
 *
 * @todo Change the AutoLoader and create a new storage for Phalcon
 * @todo Load the user_table from config or from the Users model
 */

OAuth2\Autoloader::register();

$pdo = \Phalcon\Di::getDefault()->get('db')->getInternalHandler();

$storage = new Gaia\Libraries\Oauth\Storage\Pdo($pdo, array('user_table' => 'users'));

// Pass a storage object or array of storage objects to the OAuth2 server class
$config = array(
    'access_lifetime' => 3600
);
$server = new OAuth2\Server($storage, $config);

$server->addGrantType(new OAuth2\GrantType\UserCredentials($storage));

if ($request->request('grant_type') === 'refresh_token') {
    $refreshToken = $request->request('refresh_token');
    $refreshTokenModel = (\Gaia\MVC\Models\Oauthrefreshtoken::findFirst("refresh_token = '$refreshToken'"));
    $username = $refreshTokenModel->user_id;
    $user = \Gaia\MVC\Models\User::findFirst("username = '$username'");
    $maxDate = max($user->rememberMe, $user->sessionExpires);
    $currentDate = gmdate('Y-m-d H:i:s');
    if ($maxDate > $currentDate) {
        $server->addGrantType(
            new OAuth2\GrantType\RefreshToken(
                $storage, array(
                'always_issue_new_refresh_token' => true
                )
            )
        );
    } else {
        throw new \Gaia\Exception\Access("Invalid Token");
    }
}
