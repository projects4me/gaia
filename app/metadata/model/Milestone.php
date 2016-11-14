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

$models['Milestone'] = array(
   'tableName' => 'milestones',
   'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_MILESTONES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'name' => array(
            'name' => 'name',
            'label' => 'LBL_MILESTONES_NAME',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_MILESTONES_DATE_CREATED',
            'type' => 'datetime',
            'null' => false,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_MILESTONES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => false,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_MILESTONES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
        ),
       'description' => array(
            'name' => 'description',
            'label' => 'LBL_MILESTONES_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_MILESTONES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_MILESTONES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'endDate' => array(
            'name' => 'endDate',
            'label' => 'LBL_MILESTONES_END_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'startDate' => array(
            'name' => 'startDate',
            'label' => 'LBL_MILESTONES_START_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_MILESTONES_STATUS',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
        ),
        'type' => array(
            'name' => 'type',
            'label' => 'LBL_MILESTONES_TYPE',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_MILESTONES_PROJECT',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
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
          'createdUser' => array(
              'primaryKey' => 'createdUser',
              'relatedModel' => 'User',
              'relatedKey' => 'id'
          ),
          'modifiedUser' => array(
              'primaryKey' => 'modifiedUser',
              'relatedModel' => 'User',
              'relatedKey' => 'id'
          ),
          'project' => array(
              'primaryKey' => 'projectId',
              'relatedModel' => 'Project',
              'relatedKey' => 'id'
          ),
        )
    ),
);

return $models;
