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


$models['Oauthrefreshtoken'] = array(
   'tableName' => 'oauth_refresh_tokens',
   'fields' => array(
       'refresh_token' => array(
           'name' => 'refresh_token',
           'label' => 'LBL_OAUTH_REFRESH_TOKENS_REFRESH_TOKEN',
           'type' => 'varchar',
           'length' => '40',
           'null' => false,
       ),
       'client_id' => array(
           'name' => 'client_id',
           'label' => 'LBL_OAUTH_REFRESH_TOKENS_CLIENT_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => false,
       ),
       'user_id' => array(
           'name' => 'user_id',
           'label' => 'LBL_OAUTH_REFRESH_TOKENS_USER_ID',
           'type' => 'varchar',
           'length' => '36',
           'null' => true,
       ),
       'expires' => array(
           'name' => 'expires',
           'label' => 'LBL_OAUTH_REFRESH_TOKENS_EXPIRES',
           'type' => 'datetime',
           'length' => '40',
           'null' => false,
       ),
       'scope' => array(
           'name' => 'scope',
           'label' => 'LBL_OAUTH_REFRESH_TOKENS_SCOPE',
           'type' => 'varchar',
           'length' => '2000',
           'null' => true,
       ),
    ),
    'indexes' => array(
        'refresh_token' => 'primary',
    ),
    'foriegnKeys' => array(

    ) ,
    'triggers' => array(

    ),
);

return $models;
