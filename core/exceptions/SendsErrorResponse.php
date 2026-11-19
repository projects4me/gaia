<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

use Phalcon\Http\Response;

/**
 * Sends the error response a Gaia exception builds with toResponse().
 *
 * The response is built on the shared DI response, so headers set earlier in
 * the request are kept, exactly as before toResponse() existed.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
trait SendsErrorResponse
{
    /**
     * Build the error response without sending it.
     *
     * @param Response|null $response the response to build on; a new one when null
     * @return Response
     */
    abstract public function toResponse(?Response $response = null): Response;

    /**
     * This function is used to set, send and return the http response.
     *
     * @return Response
     */
    public function handle()
    {
        $response = $this->toResponse(\Phalcon\Di\Di::getDefault()->get('response'));
        $response->send();
        return $response;
    }
}
