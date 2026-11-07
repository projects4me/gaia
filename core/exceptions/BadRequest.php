<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * Raised when the client sent a request Gaia cannot act on, e.g. a query that names an unknown field.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BadRequest extends \Exception
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
        $response->setStatusCode("400", "Bad Request");
        $response->setJsonContent(array('error' => $this->getMessage()));
        $response->send();
        return $response;
    }
}
