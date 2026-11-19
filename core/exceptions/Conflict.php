<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * The request does not match the current state of the resource, so nothing
 * was written: 409 Conflict (e.g. an embedding made from an older text, D-072).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Conflict extends \Exception
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
        return ErrorResponse::build(409, 'Conflict', [
            'error' => 'conflict',
            'error_description' => $this->getMessage()
        ], [], $response);
    }
}
