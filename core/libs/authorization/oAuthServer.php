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

$storage = new Gaia\Libraries\Oauth\Storage\Pdo($pdo,array('user_table'=>'users'));

// Pass a storage object or array of storage objects to the OAuth2 server class
$server = new OAuth2\Server($storage);


//$server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));
$server->addGrantType(new OAuth2\GrantType\UserCredentials($storage));
$server->addGrantType(new OAuth2\GrantType\RefreshToken($storage));
//$server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));