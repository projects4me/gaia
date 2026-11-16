<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Http;

use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;

/**
 * A sub-request's response: send() marks it sent but writes nothing, so a
 * controller that sends its response directly (e.g. ForgetpasswordController)
 * cannot emit headers, cookies or a body to the client from inside a
 * sub-request. The runner reads the status and content afterwards.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Http
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CapturedResponse extends Response
{
    /**
     * Mark the response sent without writing anything.
     *
     * @return ResponseInterface
     */
    public function send(): ResponseInterface
    {
        $this->sent = true;

        return $this;
    }
}
