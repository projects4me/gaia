<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * Forget password controller
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ForgetpasswordController extends RestController
{
    /**
     * This is the flag that removed Token Controller from authenication
     * check.
     *
     * @var bool
     */
    protected $authorization = false;

    /**
     * This function is used to send the password reset link to the user.
     *
     * @method postAction
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        $request = $this->getOAuthRequest();
        $email = $request->request('email');
        if ($user = $this->verifyEmail($email)) {
            $eventManager = $this->getDI()->get('eventsManager');
            $eventManager->fire('notifications:resetPassword', $user);
        }
        $this->response->setStatusCode(200, "OK");
        $this->response->setJsonContent([
            'message' => 'Password reset link has been sent to your email address.'
        ]);
        $this->response->send();
        return $this->response;
    }

    /**
     * This function is used to verify the given email address.
     *
     * @param string $email
     * @return bool
     */
    private function verifyEmail($email)
    {
        $user = \Gaia\MVC\Models\User::findFirstByEmail($email);
        return $user;
    }
}
