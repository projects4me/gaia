<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Http;

/**
 * Builds the OAuth2 request the token checks read from the current HTTP request.
 *
 * A normal request comes from the globals. A request that knows its own body
 * and cookies (an OAuthRequestSource, such as a SubRequest) builds it itself.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Http
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class OAuthRequestFactory
{
    /**
     * @param \Phalcon\Http\RequestInterface|null $request the DI request
     * @return \OAuth2\Request
     */
    public static function fromHttpRequest($request): \OAuth2\Request
    {
        if ($request instanceof OAuthRequestSource) {
            return $request->toOAuthRequest();
        }

        return \OAuth2\Request::createFromGlobals();
    }
}
