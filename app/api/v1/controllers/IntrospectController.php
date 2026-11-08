<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Agents\AgentTokenIntrospection;
use Gaia\Libraries\Authorization\TokenIntrospection;
use Gaia\Libraries\Security\AresCallerGuard;
use Gaia\Libraries\Security\IntrospectionCallerGuard;

/**
 * Introspect Controller
 *
 * Token introspection (RFC 7662 shaped). POST `token` (form field or JSON
 * body). The caller is a service, not a user, so the OAuth check is off and
 * one of two doors takes its place:
 * - a service bearer token with the `token.introspect` scope
 *   (`IntrospectionCallerGuard`): any token is introspected with its actor
 *   type, client, scope and project ids (`TokenIntrospection`, D-065);
 * - Ares's `X-Ares-Secret` header (`AresCallerGuard`): only agent tokens are
 *   active, with the agent and its tool grants (`AgentTokenIntrospection`).
 *   It stays until Ares signs in as a service client.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IntrospectController extends RestController
{
    /**
     * Callers are services, checked by the guards, not users.
     *
     * @var bool
     */
    protected $authorization = false;

    /**
     * Introspect the posted token.
     *
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\UnAuthorized When the caller is neither a service that may introspect nor Ares
     */
    public function postAction()
    {
        $aresSecret = (string) $this->request->getHeader(AresCallerGuard::HEADER);
        if ($aresSecret !== '') {
            (new AresCallerGuard())->assertCaller($aresSecret);
            $result = (new AgentTokenIntrospection())->introspect($this->requestedToken());
        } else {
            (new IntrospectionCallerGuard())->assertCaller($this->callerToken());
            $result = (new TokenIntrospection())->introspect($this->requestedToken());
        }

        $this->response->setStatusCode(200, 'OK');
        $this->response->setHeader('Cache-Control', 'no-store');
        $this->response->setJsonContent($result);

        return $this->response;
    }

    /**
     * The caller's own bearer token from the Authorization header, read the
     * way the OAuth server reads it (Apache may hide it from the request).
     *
     * @return string|null
     */
    private function callerToken()
    {
        $header = trim((string) $this->getOAuthRequest()->headers('AUTHORIZATION'));
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) !== 1) {
            return null;
        }

        return $matches[1];
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
