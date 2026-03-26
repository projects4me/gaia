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
     * Retrieve the profile picture of a user from the database and return it
     * as a JSON response containing the base64-encoded data URI.
     *
     * @method getAction
     * @param  string $id  The user ID
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\ResourceNotFound
     */
    public function getAction($id)
    {
        if (!(isset($id) && !empty($id))) {
            throw new \Phalcon\Exception('Id must be set, please refer to guides.');
        }

        $user = \Gaia\MVC\Models\User::findFirstById($id);

        if (!$user) {
            throw new \Gaia\Exception\ResourceNotFound("User not found");
        }

        if (empty($user->profilePicture)) {
            throw new \Gaia\Exception\ResourceNotFound("Profile picture not found");
        }

        // Parse "data:{mime};base64,{payload}" — fall back to image/jpeg if the
        // stored value is a raw base64 string without a data URI prefix.
        if (preg_match('/^data:([a-zA-Z\/]+);base64,/', $user->profilePicture, $matches)) {
            $mimeType   = $matches[1];
            $base64Data = substr($user->profilePicture, strpos($user->profilePicture, ',') + 1);
        } else {
            $mimeType   = 'image/jpeg';
            $base64Data = $user->profilePicture;
        }

        $imageData = base64_decode($base64Data);

        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType($mimeType);
        $this->response->setContent($imageData);

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
