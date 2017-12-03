<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
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
