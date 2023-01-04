<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Libraries\File\Handler as fileHandler;

/**
 * Image controller
 *
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ImageController extends \Phalcon\Mvc\Controller
{

    /**
     * Retrieve the image.
     *
     * @method getAction
     * @param string $id
     * @throws \Phalcon\Exception
     */
    public function getAction($id)
    {

        if (!(isset($id) && !empty($id))) {
            throw new \Phalcon\Exception('Id must be set, please refer to guides.');
        }

        $filePath = APP_PATH . DS . 'filesystem' . DS . 'img' . DS . $id;

        $data = $this->getDI()->get('fileHandler')->readFile($filePath);

        $this->response->setStatusCode(200, "OK");
        $this->response->setContent($data);
        $this->response->setContentType('image/jpeg');

        if (isset($data['error'])) {
            $this->response->setStatusCode($data['error']['code']);
        }

        $this->response->send();
        exit();
    }
}
