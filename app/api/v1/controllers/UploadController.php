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

use Foundation\Mvc\RestController;
use function Foundation\create_guid as create_guid;

/**
 * Upload controller
 *
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class UploadController extends RestController
{
  /**
   *  This function is used to get the file and place that in the filesystem
   *
   *
   */
  function postAction()
  {
    $storeFolder = 'filesystem'.DS.'uploads';   //2

    if (!empty($_FILES)) {

      $new_id = create_guid();
      $tempFile = $_FILES['file']['tmp_name'];
      $targetPath = APP_PATH . DS. $storeFolder . DS;
      $targetFile =  $targetPath. $new_id;
      if (move_uploaded_file($tempFile,$targetFile)){

        $modelName = $this->modelName;
        $model = new $modelName();

        $now = new \DateTime();

        $value = array(
          'id' => $new_id,
          'dateCreated' => $now->format('Y-m-d H:i:s'),
          'dateModified' => $now->format('Y-m-d H:i:s'),
          'createdUser' => $GLOBALS['currentUser']->id,
          'modifiedUser' => $GLOBALS['currentUser']->id,
          'status' => 'uploaded',
          'relatedId' => '--',
          'relatedTo' => '--',
          'fileType' => $_FILES['file']['type'],
          'fileSize' => $_FILES['file']['size'],
          'fileMime' => $_FILES['file']['type'],
          'filePath' => $targetFile,
          'fileDestination' => 'filesystem',
        );
        if ( $model->save($value) ){
            $dataResponse = get_object_vars($model);
            //update
            if ( isset($this->id) ){
                $this->response->setJsonContent(array('status' => 'OK'));
            //insert
            }else{
                $dataResponse['id'] = $new_id;
                $this->response->setStatusCode(201, "Created");

                $data = $model->read(array('id' => $new_id));

                $dataArray = $this->extractData($data,'one');
                $finalData = $this->buildHAL($dataArray);
                return $this->returnResponse($finalData);
            }

        }else{
            $errors = array();
            foreach( $model->getMessages() as $message )
                $errors[] = $this->language[$message->getMessage()] ? $this->language[$message->getMessage()] : $message->getMessage();

            $this->response->setJsonContent(array(
                'status' => 'ERROR',
                'messages' => $errors
            ));
        }
        return $this->response;
      } else {
        $this->response->setStatusCode(500, "Internal Server Error");
        return $this->response;
      }
    }
  }

  /**
   *  This function returns the uploaded file
   *
   *
   */
  function getAction()
  {

  }
}
