<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * This class is reponsible for handling the exceptions related to creation of permissions.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Permission extends \Exception
{
    /**
     * This function overrides php default exception class construct method and is used to
     * add some additional data into the exception.
     *
     * @method __construct
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null, $additionalData = null)
    {
        // Call the parent constructor
        parent::__construct($message, $code, $previous);

        // Additional data specific to your exception
        $this->additionalData = $additionalData;
    }

    /**
     * This function is used to set and return the http response.
     *
     * @return \Phalcon\Http\Response
     */
    public function handle()
    {
        $di = \Phalcon\Di::getDefault();
        $response = $di->get('response');
        $response->setStatusCode("422", "Access levels not allowed");
        ($this->getMessage()) && $response->setJsonContent(
            [
                "error" => $this->getMessage(),
                "suggestion" => $this->additionalData['suggestion']
            ]
        );
        $response->send();
        return $response;
    }
}
