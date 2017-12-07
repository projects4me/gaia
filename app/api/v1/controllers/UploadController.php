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
     * @method postAction
     * @todo error checking is required
     */
    function postAction()
    {
        global $logger;

        // Fetch the store path from a configuration
        $storeFolder = 'filesystem'.DS.'uploads';

        // If files were uploaded then good otherwise generate an error
        if (!empty($_FILES['file'])) {
            // create a new id for for upload
            $new_id = create_guid();

            // Setup the data required
            $tempFile = $_FILES['file']['tmp_name'];
            $targetPath = APP_PATH . DS. $storeFolder . DS;
            $targetFile =  $targetPath. $new_id;

            // Try to move the file over to the upload directory
            if (move_uploaded_file($tempFile,$targetFile)) {

                $modelName = $this->modelName;
                $model = new $modelName();

                $timeZone = new \DateTimeZone('UTC');
                $now = new \DateTime('now',$timeZone);

                $thumbnail = false;
                // If the uploaded file is an image then generate a thumbnail for it
                if ($this->_isImage($targetFile)) {
                    if ($this->_generateThumb($targetFile,$targetPath,$new_id)) {
                        $thumbnail = true;
                    }
                }

                // Set the values for the model
                $value = array(
                    'id' => $new_id,
                    'name' => $_FILES['file']['name'],
                    'dateCreated' => $now->format('Y-m-d H:i:s'),
                    'dateModified' => $now->format('Y-m-d H:i:s'),
                    'createdUser' => $GLOBALS['currentUser']->id,
                    'modifiedUser' => $GLOBALS['currentUser']->id,
                    'status' => 'uploaded',
                    'relatedId' => $_REQUEST['relatedId'],
                    'relatedTo' => $_REQUEST['relatedTo'],
                    'fileType' => $_FILES['file']['type'],
                    'fileSize' => $_FILES['file']['size'],
                    'fileMime' => $_FILES['file']['type'],
                    'filePath' => $targetFile,
                    'fileDestination' => 'filesystem',
                    'fileThumbnail' => $thumbnail
                );

                if ($model->save($value)) {
                    // if we were able to save the model then send the OK status
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

                        $logger->debug("File with ID ".$new_id." uploaded, returning response");

                        return $this->returnResponse($finalData);
                    }

                }else{
                    // Otherwise consolidate the errors and display
                    $errors = array();
                    foreach( $model->getMessages() as $message ) {
                        $errors[] = $this->language[$message->getMessage()] ? $this->language[$message->getMessage()] : $message->getMessage();
                    }

                    $logger->error("Unable to upload the file");
                    $logger->error(print_r($errors,1));

                    $this->response->setJsonContent(array(
                        'status' => 'ERROR',
                        'messages' => $errors
                    ));
                }
                return $this->response;
            } else {
                $logger->error('Unable to move the file over to the upload directory, please make'.
                                ' sure that the directory exists and that its writable');
                $this->response->setStatusCode(500, "Internal Server Error");
                return $this->response;
            }
        }
        else
        {
            $logger->error('No file was found in the request, please make sure that you'.
                            ' upload one and it must be named file.');
            $this->response->setStatusCode(400, "Bad Request. File not found");
            return $this->response;
        }
    }

    /**
     * This function checks if the uploaded file is an image or not
     *
     * ```php
     * $this->_isImage('/where/the/file/is/placed');
     * ```
     *
     * @param string $file The full filename, this file must be readable as this function will check the contents
     * @return bool isImage whether the file is an image or not
     */
    protected function _isImage(string $file): bool
    {
        global $logger;

        $logger->debug('Gaia.Controllers.Upload->_isImage');
        // The list of supported image files
        $supportedTypes = array(
            IMAGETYPE_GIF,
            IMAGETYPE_JPEG,
            IMAGETYPE_PNG
        );

        // Make sure that we are able to read the file
        if($fileType = exif_imagetype($file)) {

            $logger->debug('File with path ' . $file . ' provided with file type ' . $fileType);

            if (in_array($fileType, $supportedTypes)) {

                $logger->debug('which is supported');
                $logger->debug('-Gaia.Controllers.Upload->_isImage');
                return true;
            }
        } else {
            $logger->error('Could not load the file information');
            $logger->debug('-Gaia.Controllers.Upload->_isImage');
            return false;
        }

        $logger->debug('which is not supported');
        $logger->debug('-Gaia.Controllers.Upload->_isImage');
        return false;
    }


    /**
     * This function creates the thumbnail of the uploaded images
     *
     * Note: This function is not responsible to check for files contents, it assumes that
     * the file provided to it is an image file. It is the responsibility of the caller to
     * make sure that an image file in indeed sent
     *
     * @method _generateThumb
     * @param string $file This is the full file name from where the image is to be loaded
     * @param string $path This is the upload directory path
     * @param string $id This is the identifier of the Upload record
     * @return bool
     * @protected
     */
    protected function _generateThumb(string $file,string $path,string $id): bool
    {
        global $logger;

        $logger->debug('Gaia.Api.Controller.Upload->_generateThumb');

        try {
            // First check for the imaagick extension
            $image = new \Phalcon\Image\Adapter\Imagick($file);
            if (!$image->check()) {
                // If it not available then try to use GD
                $image = new \Phalcon\Image\Adapter\GD($file);
                if (!$image->check()) {
                    $logger->critical('UploadController->_generateFile :: Imagick or GD extentions nor found. Please install either of the extensions.');
                    throw new Exception('Required extension not found. Please make sure that either GD or Imagick (preferred) PHP extension is available.');
                } else {
                    $logger->debug('Using GD to generate the thumbnail');
                }
            } else {
                $logger->debug('Using Imagick to generate the thumbnail');
            }

            $width = $image->getWidth();
            $height = $image->getHeight();
            $ratio = $width/$height;

            if ($ratio == 1.5) {
                $logger->debug('Ratio is equal to 1.5');
                $image->resize(198,132);
            } else if ($ratio > 1.5) {
                $logger->debug('Ratio is greater than 1.5');
                $logger->debug('Resizing to '.(ceil(132*$ratio)).',132');
                $image->resize((ceil(132*$ratio)),132)->crop(198,132);
            } else {
                $logger->debug("Ratio is less to 1.5");
                $logger->debug('Resizing to 198,'.(floor(198/$ratio)));
                $image->resize(198,(floor(198/$ratio)))->crop(198,132);
            }

            $image->save($path.DS."thumbnail/thumb_" . $id.'.jpg');
            return true;
        } catch (Exception $e) {
        }

        $logger->debug('-Gaia.Api.Controller.Upload->_generateThumb');
        return false;
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
