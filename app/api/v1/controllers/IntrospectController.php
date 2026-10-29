<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Agents\AgentTokenIntrospection;
use Gaia\Libraries\Security\AresCallerGuard;

/**
 * Introspect Controller
 *
 * Token introspection for Ares (RFC 7662 shaped). POST `token` (form field or
 * JSON body) with the `X-Ares-Secret` header; the response says whether the
 * token is an active agent token and, if it is, carries the agent and its
 * tool grants (see `AgentTokenIntrospection`). The caller is the Ares
 * service, not a user, so the OAuth check is off and `AresCallerGuard`
 * takes its place.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IntrospectController extends RestController
{
    /**
     * Ares authenticates with its service secret, not an OAuth token.
     *
     * @var bool
     */
    protected $authorization = false;

    /**
     * Introspect the posted token.
     *
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\UnAuthorized When the caller is not Ares
     */
    public function postAction()
    {
        (new AresCallerGuard())->assertCaller($this->request->getHeader(AresCallerGuard::HEADER));

        $result = (new AgentTokenIntrospection())->introspect($this->requestedToken());

        $this->response->setStatusCode(200, 'OK');
        $this->response->setHeader('Cache-Control', 'no-store');
        $this->response->setJsonContent($result);

        return $this->response;
    }

    /**
     * The `token` from a form post or a JSON body.
     *
     * @return string|null
     */
    private function requestedToken()
    {
        $token = $this->request->getPost('token');
        if ($token === null) {
            $body = $this->request->getJsonRawBody(true);
            $token = is_array($body) && isset($body['token']) ? $body['token'] : null;
        }

        return is_string($token) ? $token : null;
    }
}
