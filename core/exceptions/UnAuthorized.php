<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * This class is reponsible for handling the exceptions related to user un-authorization.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class UnAuthorized extends \Exception
{
    use SendsErrorResponse;

    /**
     * Build the http response without sending it. An empty message leaves the body empty.
     *
     * @param \Phalcon\Http\Response|null $response the response to build on; a new one when null
     * @return \Phalcon\Http\Response
     */
    public function toResponse(?\Phalcon\Http\Response $response = null): \Phalcon\Http\Response
    {
        $body = $this->getMessage() ? ['error' => $this->getMessage()] : null;
        return ErrorResponse::build(401, 'Unauthorized', $body, [], $response);
    }
}