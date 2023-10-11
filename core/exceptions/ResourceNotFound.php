<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * This class is reponsible for handling the exceptions related to resource not found.
 * 
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ResourceNotFound extends \Exception
{
    /**
     * This function is used to set and return the http response.
     * 
     * @return \Phalcon\Http\Response
     */
    public function handle()
    {
        $di = \Phalcon\Di::getDefault();
        $response = $di->get('response');
        $response->setStatusCode("404", "Resource Not found");
        ($this->getMessage()) && $response->setJsonContent(array('error' => $this->getMessage()));
        $response->send();
        return $response;
    }
}