<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\MVC\REST\Controllers\RestController;
use function Gaia\Libraries\Utils\create_guid as create_guid;

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

    public $uses = array('Fileattachment');
    /**
     * Get method is not supported for upload
     *
     * @method getAction
     */
    function getAction()
    {
        global $logger;
        $logger->debug("Gaia.Controllers.Upload->getAction");

        if (!(isset($this->id) && !empty($this->id)))
        {
            throw new \Phalcon\Exception('Id must be set, please refer to guides.');
        }

        $model = new Upload;
        $data = $model->read(['id' => $this->id]);

        if (isset($data[0]) and !empty($data[0]->id)) {
            if (file_exists($data[0]->filePath)) {

                $this->response->setStatusCode(200, "OK");
                $this->response->setContentType($data[0]->fileType);
                $this->response->setHeader("Expires", '0');
                $this->response->setHeader("Content-Type", $data[0]->fileType);
                $this->response->setHeader("Cache-Control", 'must-revalidate');
                $this->response->setHeader("Content-Length", $data[0]->fileSize);
                readfile($data[0]->filePath);
            } else {
                $this->response->setStatusCode(500, "Internal Server Error");
            }
        } else {
            $this->response->setStatusCode(400, "Not Found");
        }

        $this->response->send();
        $logger->debug("-Gaia.Controllers.Upload->getAction");
        exit();
    }
    /**
     * This is the list action for uploads, it is used to either get the preview
     * of the uploads or download them.
     *
     * @method listAction
     */
    function listAction()
    {
        global $logger;
        $logger->debug("Gaia.Controllers.Upload->listAction");

        $id = $this->request->get('id',null,null);
        if (!(isset($id) && !empty($id)))
        {
            throw new \Phalcon\Exception('Id must be set, please refer to guides.');
        }

        // Check if the user wants to download the files of not
        $download = $this->request->get('download',null,false);
        $timeZone = new \DateTimeZone('UTC');
        $now = new \DateTime('now',$timeZone);

        $model = new Upload;
        $data = $model->read(['id' => $id]);
        $dataArray = $this->extractData($data);
        $this->finalData = $this->buildHAL($dataArray);

        if ($download) {
            // generate the downloadlink and return it along with the file data
            $downloadLink = new Downloadtoken;

            $expiry = new \DateTime('now',$timeZone);;
            $expiry->add(new \DateInterval('PT10M'));

            // Set the values for the model
            $values = array(
                'downloadToken' => create_guid(),
                'dateCreated' => $now->format('Y-m-d H:i:s'),
                'createdUser' => $GLOBALS['currentUser']->id,
                'uploadId' => $this->finalData['data'][0]['id'],
                'relatedId' => $this->finalData['data'][0]['attributes']['relatedId'],
                'relatedTo' => $this->finalData['data'][0]['attributes']['relatedTo'],
                'expires' => $expiry->format('Y-m-d H:i:s')
            );

            // Save

            if ($downloadLink->save($values)){
                $this->finalData['data'][0]['attributes']['downloadLink'] = $values['downloadToken'];
            }
        }


        $this->eventsManager->fire('rest:afterRead', $this);

        $logger->debug("-Gaia.Controllers.Upload->listAction");
        return $this->returnResponse($this->finalData);
    }


    /**
     * This is the delete action
     *
     * @method getAction
     * @todo possibility have a recycle bin concept
     * @todo have the thumbnail folder in the config
     * @todo refactor
     */
    function deleteAction()
    {
        global $logger;
        $logger->debug("Gaia.Controllers.Upload->deleteAction()");

        // Fetch, handle and then call the deletion action
        $modelName = $this->modelName;
        $model = $modelName::findFirst('id = "'.$this->id.'"');

        //delete if the object exists
        if ( $model!=false ){

            $id = $model->id;
            $filePath = $model->filePath;
            $thumbnail = $model->fileThumbnail;

            if ( $model->delete() == true ) {
                $this->eventsManager->fire('rest:afterDelete', $this, $model);
                // remove the file
                unlink($filePath);

                // if thumbnail existed then remove that as well
                if ($thumbnail)
                {
                    $thumbPath = APP_PATH . DS. 'filesystem'.DS.'uploads' . DS.
                        "thumbnail/thumb_" . $id. '.jpg';
                    unlink($thumbPath);
                }


                $this->response->setJsonContent(array('data' => array('type' => strtolower($modelName),"id"=>$this->id)));
                $this->response->setStatusCode(200, "OK");
            }else{
                $this->response->setStatusCode(500, "Internal Server Error, could not delete the upload");

                $errors = array();
                foreach( $model->getMessages() as $message )
                    $errors[] = $this->language[$message->getMessage()] ? $this->language[$message->getMessage()] : $message->getMessage();

                $this->response->setJsonContent(array('status' => "ERROR", 'messages' => $errors));
            }
        }else{
            $this->response->setStatusCode(404, "Not found");
            $this->response->setJsonContent(array('status' => "ERROR", 'messages' => array("These are not the records you are looking for!")));
        }

        return $this->response;

        $logger->debug("-Gaia.Controllers.Upload->deleteAction()");
    }

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
                $model->assign($value);
                $model->save();
                if ($model->save($value)) {
                    $this->eventsManager->fire('rest:afterCreate', $this, $model);
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


                        //$thumbdata = 'data:'.$value['attributes']['fileMime'].';base64,'.base64_encode(file_get_contents($targetPath));
                        $dataArray = $this->extractData($data,'one');
                        $finalData = $this->buildHAL($dataArray);

                        if ($thumbnail) {
                            $thumbPath = APP_PATH . DS. 'filesystem'.DS.'uploads' . DS.
                                "thumbnail/thumb_" . $finalData['data']['id']. '.jpg';

                            $thumbdata = 'data:'.$_FILES['file']['type'].';base64,'.base64_encode(file_get_contents($thumbPath));
                            $finalData['data']['attributes']['fileThumbnail'] = $thumbdata;
                        }

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
}
