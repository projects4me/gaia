<?php

/* 
 * Projects4Me Community Edition is an open source project management software 
 * developed by PROJECTS4ME Inc. Copyright (C) 2015-2016 PROJECTS4ME Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 (GNU AGPL v3) as
 * published be the Free Software Foundation with the addition of the following 
 * permission added to Section 15 as permitted in Section 7(a): FOR ANY PART OF 
 * THE COVERED WORK IN WHICH THE COPYRIGHT IS OWNED BY PROJECTS4ME Inc., 
 * Projects4Me DISCLAIMS THE WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU AGPL v3 for more details.
 * 
 * You should have received a copy of the GNU AGPL v3 along with this program; 
 * if not, see http://www.gnu.org/licenses or write to the Free Software 
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 * 
 * You can contact PROJECTS4ME, Inc. at email address contact@projects4.me.
 * 
 * The interactive user interfaces in modified source and object code versions 
 * of this program must display Appropriate Legal Notices, as required under 
 * Section 5 of the GNU AGPL v3.
 * 
 * In accordance with Section 7(b) of the GNU AGPL v3, these Appropriate Legal 
 * Notices must retain the display of the "Powered by Projects4Me" logo. If the 
 * display of the logo is not reasonably feasible for technical reasons, the 
 * Appropriate Legal Notices must display the words "Powered by Projects4Me".
 */

/**
 * @todo Change the AutoLoader and create a new storage for Phalcon
 * @todo Load the user_table from config or from the Users model
 */

require_once(APP_PATH.'/vendor/OAuth2/Autoloader.php');
OAuth2\Autoloader::register();

$pdo = \Phalcon\Di::getDefault()->get('db')->getInternalHandler();

$storage = new OAuth2\Storage\Pdo($pdo,array('user_table'=>'users'));

// Pass a storage object or array of storage objects to the OAuth2 server class
$server = new OAuth2\Server($storage);


$server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));
$server->addGrantType(new OAuth2\GrantType\UserCredentials($storage));

$server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));