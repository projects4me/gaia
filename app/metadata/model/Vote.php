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

$models['Vote'] = array(
   'tableName' => 'votes',
   'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_VOTE_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'dateCreated' => array(
            'name' => 'dateCreated',
            'label' => 'LBL_VOTE_DATE_CREATED',
            'type' => 'datetime',
            'null' => true,
        ),
        'dateModified' => array(
            'name' => 'dateModified',
            'label' => 'LBL_VOTE_DATE_MODIFIED',
            'type' => 'datetime',
            'null' => true,
        ),
        'createdUser' => array(
            'name' => 'createdUser',
            'label' => 'LBL_VOTE_CREATED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'createdUserName' => array(
            'name' => 'createdUserName',
            'label' => 'LBL_VOTE_CREATED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'modifiedUser' => array(
            'name' => 'modifiedUser',
            'label' => 'LBL_VOTE_MODIFIED_USER',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'modifiedUserName' => array(
            'name' => 'modifiedUserName',
            'label' => 'LBL_VOTE_MODIFIED_USER_NAME',
            'type' => 'varchar',
            'length' => '50',
            'null' => false,
        ),
        'vote' => array(
            'name' => 'vote',
            'label' => 'LBL_VOTE_VOTE',
            'type' => 'bool',
            'null' => false,
        ),
        'relatedTo' => array(
            'name' => 'relatedTo',
            'label' => 'LBL_VOTE_RELATED_TO',
            'type' => 'varchar',
            'length' => '20',
            'null' => false,
        ),
        'relatedId' => array(
            'name' => 'relatedId',
            'label' => 'LBL_VOTE_RELATED_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
        ),
        'relatedName' => array(
            'name' => 'relatedName',
            'label' => 'LBL_VOTE_RELATED_NAME',
            'type' => 'varchar',
            'length' => '50',
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
        'createdBy' => array(
          'primaryKey' => 'createdUser',
          'relatedModel' => 'User',
          'relatedKey' => 'id',
        ),
        'modifiedBy' => array(
          'primaryKey' => 'modifiedUser',
          'relatedModel' => 'User',
          'relatedKey' => 'id',
        )
      ),
      'belongsTo' => array(
          'wiki' => array(
              'primaryKey' => 'relatedId',
              'relatedModel' => 'Wiki',
              'relatedKey' => 'id',
              'condition' => 'Vote.relatedTo = "wiki"'
          ),
          'comment' => array(
              'primaryKey' => 'relatedId',
              'relatedModel' => 'Comment',
              'relatedKey' => 'id',
              'condition' => 'Vote.relatedTo = "comment"'
          ),
          'conversationroom' => array(
              'primaryKey' => 'relatedId',
              'relatedModel' => 'Conversationroom',
              'relatedKey' => 'id',
              'condition' => 'Vote.relatedTo = "conversationroom"'
          )
      ),
    ),
);

return $models;
