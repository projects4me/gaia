<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils\Http\Fake;

use Gaia\Core\MVC\Http\OAuthRequestFactory;
use Phalcon\Http\Response;

/**
 * A fake REST controller for SubRequestRunnerTest. Like RestController, it
 * authenticates in initialize() from the Authorization header and sets the
 * global currentUser; "Bearer denied" is refused with a Gaia exception.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProbeController extends \Phalcon\Mvc\Controller
{
    /**
     * @var int how many instances ran initialize()
     */
    public static $initializations = 0;

    /**
     * @var string the user this instance authenticated
     */
    private $user;

    /**
     * Authenticate from the Authorization header, as RestController::authorize() does.
     *
     * @return void
     * @throws \Gaia\Exception\Access when the token is "denied"
     */
    public function initialize()
    {
        self::$initializations++;
        $header = (string) $this->request->getHeader('Authorization');
        if ($header === 'Bearer denied') {
            throw new \Gaia\Exception\Access('Invalid Token');
        }
        $this->user = $header;
        $GLOBALS['currentUser'] = $header;
    }

    /**
     * Echo what the controller sees of the request.
     *
     * @return Response
     */
    public function echoAction()
    {
        $response = new Response();
        $response->setStatusCode(200, 'OK');
        $response->setJsonContent([
            'method' => $this->request->getMethod(),
            'uri' => $this->request->getURI(),
            'query' => $this->request->get('query'),
            'page' => $this->request->getQuery('page', 'int'),
            'url' => $this->request->getQuery('_url'),
            'body' => $this->request->getJsonRawBody(true),
            'user' => $this->user,
            'oauthAuthorization' => OAuthRequestFactory::fromHttpRequest($this->request)->headers('AUTHORIZATION'),
            'oauthUser' => OAuthRequestFactory::fromHttpRequest($this->request)->headers('PHP_AUTH_USER'),
            'oauthPassword' => OAuthRequestFactory::fromHttpRequest($this->request)->headers('PHP_AUTH_PW'),
            'id' => $this->dispatcher->getParam('id'),
            'cookies' => array_merge($_COOKIE, OAuthRequestFactory::fromHttpRequest($this->request)->cookies),
        ]);

        return $response;
    }

    /**
     * Refuse with a Gaia exception, as an ACL check does.
     *
     * @return void
     * @throws \Gaia\Exception\Permission
     */
    public function forbiddenAction()
    {
        throw new \Gaia\Exception\Access('Access Denied');
    }

    /**
     * Fail with an unexpected error.
     *
     * @return void
     * @throws \RuntimeException
     */
    public function boomAction()
    {
        throw new \RuntimeException('secret detail at /var/www/html');
    }

    /**
     * Send the response directly, as ForgetpasswordController::postAction()
     * does; the runner must capture it without writing to the client.
     *
     * @return Response
     */
    public function sendAction()
    {
        $this->response->setStatusCode(201, 'Created');
        $this->response->setJsonContent(['sent' => false]);
        $this->response->send();

        return $this->response;
    }
}
