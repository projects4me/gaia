<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

/**
 * User Image controller
 *
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class UserimageController extends \Phalcon\Mvc\Controller
{

    /**
     * Retrieve the user and image and return
     *
     * @method getAction
     * @param string $id
     * @todo Handle validation
     * @todo Generate Image
     * @throws \Phalcon\Exception
     */
    public function getAction($id)
    {
        if (!(isset($id) && !empty($id))) {
            throw new \Phalcon\Exception('Id must be set, please refer to guides.');
        }

        $filePath = APP_PATH . DS . 'filesystem' . DS . 'img' . DS . 'user' . DS . $id;

        if (!file_exists($filePath)) {
            $filePath = APP_PATH . DS . 'public' . DS . 'img' . DS . 'Reddit.png';
        }

        $data = readfile($filePath);

        $this->response->setStatusCode(200, "OK");
        $this->response->setContent($data);
        $this->response->setContentType('image/jpeg');

        if (isset($data['error'])) {
            throw new \Gaia\Exception\FileNotFound("File not found");
        }

        $this->response->send();
        return $this->response;
    }

    /**
     * This function is used to save user's image, that is temporary uploaded by user, in 
     * tmp directory.
     * 
     * @return \Phalcon\Http\Response
     */
    function postAction()
    {
        global $logger;

        $id = $_REQUEST['id'];

        $imagePath = APP_PATH . DS . 'filesystem' . DS . 'tmp' . DS . 'img' . DS . 'user' . DS . $id;

        /**
         * Save image of user inside temp folder until user doesn't saves the form. If user save the form
         * then that image, inside temp folder, will be moved to img/user/ directory.
         */
        $imageFile = $_FILES['file']['tmp_name'];

        if (move_uploaded_file($imageFile, $imagePath)) {
            $this->response->setStatusCode(201, "Created");
        } else {
            $logger->error('Unable to move the file over to the upload directory, please make' .
                ' sure that the directory exists and that its writable');
            throw new \Gaia\Exception\FileNotFound();
        }

        return $this->response;
    }
}
