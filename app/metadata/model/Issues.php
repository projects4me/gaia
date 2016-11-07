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

$models['Issues'] = array(
   'tableName' => 'issues',
   'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_ISSUES_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'subject' => array(
            'name' => 'subject',
            'label' => 'LBL_ISSUES_SUBJECT',
            'type' => 'varchar',
            'length' => '255',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_ISSUES_DATE_CREATED',
            'type' => 'datetime',
            'null' => false,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_ISSUES_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => false,
        ),
        'deleted' => array(
            'name' => 'deleted',
            'label' => 'LBL_ISSUES_DELETED',
            'type' => 'bool',
            'length' => '1',
            'null' => false,
        ),
       'description' => array(
            'name' => 'description',
            'label' => 'LBL_ISSUES_DESCRIPTION',
            'type' => 'text',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_ISSUES_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'owner' => array(
            'name' => 'owner',
            'label' => 'LBL_ISSUES_OWNER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'assignee' => array(
            'name' => 'assignee',
            'label' => 'LBL_ISSUES_ASSIGNEE',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'reportedUser' => array(
            'name' => 'reportedUser',
            'label' => 'LBL_ISSUES_REPORTED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_ISSUES_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'issueNumber' => array(
            'name' => 'issueNumber',
            'label' => 'LBL_ISSUES_ISSUE_NUMBER',
            'type' => 'int',
            'length' => '11',
            'null' => false,
        ),
        'endDate' => array(
            'name' => 'endDate',
            'label' => 'LBL_ISSUES_END_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'startDate' => array(
            'name' => 'startDate',
            'label' => 'LBL_ISSUES_START_DATE',
            'type' => 'date',
            'null' => true,
        ),
        'status' => array(
            'name' => 'status',
            'label' => 'LBL_ISSUES_STATUS',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
        ),
        'typeId' => array(
            'name' => 'typeId',
            'label' => 'LBL_ISSUES_TYPE',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'priority' => array(
            'name' => 'priority',
            'label' => 'LBL_ISSUES_PRIORITY',
            'type' => 'varchar',
            'length' => '25',
            'null' => false,
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_ISSUES_PROJECT',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'milestoneId' => array(
            'name' => 'milestoneId',
            'label' => 'LBL_ISSUES_MILESTONE',
            'type' => 'varchar',
            'length' => '36',
            'null' => true,
        ),
        'parentId' => array(
            'name' => 'parentId',
            'label' => 'LBL_ISSUES_PARENT',
            'type' => 'varchar',
            'length' => '36',
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
          'Assignee' => array(
              'primaryKey' => 'assignee',
              'relatedModel' => 'Users',
              'relatedKey' => 'id'
          ),
          'CreatedUser' => array(
              'primaryKey' => 'createdUser',
              'relatedModel' => 'Users',
              'relatedKey' => 'id'
          ),
          'ModifiedUser' => array(
              'primaryKey' => 'modifiedUser',
              'relatedModel' => 'Users',
              'relatedKey' => 'id'
          ),
          'Owner' => array(
              'primaryKey' => 'owner',
              'relatedModel' => 'Users',
              'relatedKey' => 'id'
          ),
          'ReportedUser' => array(
              'primaryKey' => 'reportedUser',
              'relatedModel' => 'Users',
              'relatedKey' => 'id'
          ),
          'Project' => array(
              'primaryKey' => 'projectId',
              'relatedModel' => 'Projects',
              'relatedKey' => 'id'
          ),
        )
    ),
);

return $models;
