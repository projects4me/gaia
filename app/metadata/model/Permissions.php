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

$models['Permissions'] = array(
   'tableName' => 'permissions',
   'fields' => array(
       'id' => array(
           'name' => 'id',
           'label' => 'LBL_PERMISSIONS_ID',
           'type' => 'int',
           'length' => '11',
           'null' => false,
       ),
       'roleId' => array(
           'name' => 'roleId',
           'label' => 'LBL_PERMISSSIONS_ROLE_ID',
           'type' => 'int',
           'length' => '11',
           'null' => false,
       ),
       'resourceId' => array(
           'name' => 'resourceId',
           'label' => 'LBL_PERMISSSIONS_RESOURCE_ID',
           'type' => 'int',
           'length' => '11',
           'null' => false,
       ),
       '_read' => array(
           'name' => '_read',
           'label' => 'LBL_PERMISSIONS_READ',
           'type' => 'int',
           'length' => '1',
           'null' => true,
       ),
       '_search' => array(
           'name' => '_search',
           'label' => 'LBL_PERMISSIONS_SEARCH',
           'type' => 'int',
           'length' => '1',
           'null' => true,
       ),
       '_create' => array(
           'name' => '_create',
           'label' => 'LBL_PERMISSIONS_CREATE',
           'type' => 'int',
           'length' => '1',
           'null' => true,
       ),
       '_update' => array(
           'name' => '_update',
           'label' => 'LBL_PERMISSIONS_UPDATE',
           'type' => 'int',
           'length' => '1',
           'null' => true,
       ),
       '_delete' => array(
           'name' => '_delete',
           'label' => 'LBL_PERMISSIONS_DELETE',
           'type' => 'int',
           'length' => '1',
           'null' => true,
       ),
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(
       
    ) ,
    'triggers' => array(
        
    ),
    'relationships' => array(
        'hasOne' => array(
            'Resource' => array(
                'primaryKey' => 'resourceId',
                'relatedModel' => 'Resources',
                'relatedKey' => 'id',
            ),
            'Role' => array(
                'primaryKey' => 'roleId',
                'relatedModel' => 'Roles',
                'relatedKey' => 'id',
            )
        ),
    ),
);

return $models;
