<?php

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Utils\Util;
use Gaia\MVC\Models\Behaviors\encryptPasswordBehavior;

class ResetpasswordController extends \Phalcon\Mvc\Controller
{
    /**
     * This is the flag that removed Token Controller from authenication
     * check.
     *
     * @var bool
     */
    protected $authorization = false;

    /**
     * This function is used to validate the token.
     *
     * @method listAction
     *
     */
    public function listAction()
    {
        $validateTokenMap = [
            "reset" => "validateResetToken"
        ];

        $tokenType = $this->getOAuthRequest()->query('validateTokenType');
        $token = $this->getOAuthRequest()->query('token');

        if (!isset($tokenType) || !isset($token)) {
            throw new \Gaia\Exception\UnAuthorized("Token type and token are required");
        }

        if (isset($validateTokenMap[$tokenType])
           && $this->{$validateTokenMap[$tokenType]}($token)) {
            $this->response->setJsonContent([
                'valid' => "true",
                'message' => 'Token is valid'
            ]);
            $this->response->setStatusCode(200, "OK");
        } else {
            throw new \Gaia\Exception\UnAuthorized("Invalid token type");
        }

        return $this->response;
    }


    /**
     * This function is used to reset the user's password.
     *
     * @method patchAction
     */
    public function patchAction()
    {
        $util = new Util();
        $data = $util->objectToArray($this->request->getJsonRawBody());
        $encryptPasswordBehavior = new encryptPasswordBehavior();
        $resetToken = $data['resetToken'];
        $password = $data['password'];

        if ($this->validateResetToken($resetToken) && isset($password)) {
            $passwordHash = $encryptPasswordBehavior->createHash($password);

            $this->updateUserPassword($resetToken, $passwordHash);
        } else {
            throw new \Gaia\Exception\Exception();
        }

        return $this->response;
    }

    /**
     * This function is used to validate the reset token.
     *
     * @method validateResetToken
     * @param  string $token
     *
     */
    public function validateResetToken($token)
    {
        $user = \Gaia\MVC\Models\User::findFirstByResetToken($token);
        if ($user->resetTokenExpiry < gmdate('Y-m-d H:i:s')) {
            throw new \Gaia\Exception\UnAuthorized("Token has expired");
        }

        $this->response->setJsonContent([
            'valid' => "true",
            'message' => 'Token is valid'
        ]);
        $this->response->setStatusCode(200, "OK");

        return $this->response;
    }

    /**
     * This function is used to update the user's password in the database.
     *
     * @method updateUserPassword
     * @param  string $resetToken
     * @param  string $passwordHash
     */
    private function updateUserPassword($resetToken, $passwordHash)
    {
        $user = \Gaia\MVC\Models\User::findFirstByResetToken($resetToken);
        $user->password = $passwordHash;
        $user->resetToken = null;
        $user->resetTokenExpiry = null;
        $user->save();

        $this->response->setJsonContent([
            'message' => 'Password has been reset successfully'
        ]);

        $this->response->setStatusCode(200, "OK");
    }
}
