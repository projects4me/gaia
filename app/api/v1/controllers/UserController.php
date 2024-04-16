<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * User controller
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class UserController extends RestController
{
    public $uses = ['Globalrole', 'Dashboard'];
    
    /**
     * This function updates user record and move user's profile from temp directory to its original
     * directory from where we deliver images to client side.
     * 
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        global $logger;
        $response = parent::patchAction();

        $tempImagePath = APP_PATH . DS . 'filesystem' . DS . 'tmp' . DS . 'img' . DS . 'user' . DS . $this->id;
        //Check if user selected image exists in temp directory. If exists then move it to filesystem/img/user/ directory
        if (file_exists($tempImagePath)) {
            $targetPath = APP_PATH . DS . 'filesystem' . DS . 'img' . DS . 'user' . DS . $this->id;

            if (!rename($tempImagePath, $targetPath)) {
                $logger->error(
                    'Unable to move the file over to the filesystem/img/user directory, please make' .
                    ' sure that the directory exists and that its writable'
                );
                $this->response->setStatusCode(500, "Internal Server Error");
            }
        }
        return $response;
    }
}
