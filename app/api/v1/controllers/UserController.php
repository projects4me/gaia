<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Utils\Util;

/**
 * User controller
 *
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class UserController extends RestController
{
    /**
     * This function saves a user, which is done via the RestController. Before saving the
     * user model, we're hashing the password of the user by using sha1() alogrithm. The
     * reason to use sha1 is that our oauth library uses that algorithm to matches the password.
     *
     * @method postAction
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        $util = new Util();
        $requestData = $util->objectToArray($this->request->getJsonRawBody());

        //hash password
        $requestData['password'] = sha1($requestData['password']);

        //pass data to postAction method of parent class
        $response = parent::postAction($requestData);

        return $response;
    }
}
