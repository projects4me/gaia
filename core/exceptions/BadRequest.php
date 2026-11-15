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
    use SendsErrorResponse;

    /**
     * Build the http response without sending it.
     *
     * @param \Phalcon\Http\Response|null $response the response to build on; a new one when null
     * @return \Phalcon\Http\Response
     */
    public function toResponse(?\Phalcon\Http\Response $response = null): \Phalcon\Http\Response
    {
        return ErrorResponse::build(400, 'Bad Request', ['error' => $this->getMessage()], [], $response);
    }
}
