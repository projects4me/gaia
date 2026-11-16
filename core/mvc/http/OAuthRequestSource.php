<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Http;

/**
 * An HTTP request that builds its own OAuth2 request, for requests whose
 * body or cookies are not the ones in php://input and the globals.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Http
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface OAuthRequestSource
{
    /**
     * @return \OAuth2\Request the request the token checks read
     */
    public function toOAuthRequest(): \OAuth2\Request;
}
