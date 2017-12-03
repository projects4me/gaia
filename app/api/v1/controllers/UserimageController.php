<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

use Foundation\fileHandler;

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
   * retrive the user and image and return
   *
   * @return Image
   * @todo Handle validation
   * @todo Generate Image
   */
  public function getAction($id){

      $modelName = 'User';

      if (!(isset($id) && !empty($id)))
      {
          throw new \Phalcon\Exception('Id must be set, please refer to guides.');
      }

      $filePath = APP_PATH.DS.'filesystem'.DS.'img'.DS.'user'.DS.$id;

      if (!file_exists($filePath))
      {
        $filePath = APP_PATH.DS.'public'.DS.'img'.DS.'Reddit.png';
      }

      $data = fileHandler::readFile($filePath);

      $this->response->setStatusCode(200, "OK");
      $this->response->setContent($data);
      $this->response->setContentType('image/jpeg');

      if (isset($data['error']))
      {
          $this->response->setStatusCode($data['error']['code']);
      }

      $this->response->send();
      exit();
  }

}
