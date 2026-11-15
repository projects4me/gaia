<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

use Phalcon\Http\Response;

/**
 * Builds the HTTP error response for a Gaia exception without sending it.
 *
 * The status, the optional JSON body and any extra headers are written onto the
 * given response, or onto a new one, so a caller can either send it (a normal
 * request) or keep it as one result among many (a bulk request).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
final class ErrorResponse
{
    /**
     * @param int $status HTTP status code
     * @param string $reason HTTP reason phrase
     * @param array|null $body JSON body; null leaves the content untouched
     * @param array $headers extra headers, name => value
     * @param Response|null $response the response to build on; a new one when null
     * @return Response
     */
    public static function build(
        int $status,
        string $reason,
        ?array $body = null,
        array $headers = [],
        ?Response $response = null
    ): Response {
        $response = $response ?? new Response();
        $response->setStatusCode($status, $reason);
        foreach ($headers as $name => $value) {
            $response->setHeader($name, $value);
        }
        if ($body !== null) {
            $response->setJsonContent($body);
        }
        return $response;
    }
}
