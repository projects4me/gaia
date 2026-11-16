<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Http;

use Gaia\Exception\PhalconError;
use Phalcon\Di\DiInterface;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Http\Response;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\RouterInterface;

/**
 * Runs one ordinary REST call inside the current PHP request and captures
 * its status and body without sending anything to the client.
 *
 * The call goes through the normal router, dispatcher and controller, so its
 * authentication and ACL checks run exactly as for a separate HTTP call, with
 * the Authorization header given here. Calls run one at a time; each gets its
 * own request, response, router and dispatcher, and the outer request's
 * state is restored afterwards, also when the call throws (see
 * docs/architecture/bulk/decisions.md).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Http
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SubRequestRunner
{
    /**
     * @var DiInterface
     */
    private $di;

    /**
     * @var callable(): RouterInterface
     */
    private $routerFactory;

    /**
     * @param DiInterface $di the application container
     * @param callable|null $routerFactory builds a fresh router; the Gaia REST router when null
     */
    public function __construct(DiInterface $di, ?callable $routerFactory = null)
    {
        $this->di = $di;
        $this->routerFactory = $routerFactory ?? static function (): RouterInterface {
            $router = new \Gaia\MVC\Router();
            $router->init();
            return $router;
        };
    }

    /**
     * Run one call.
     *
     * @param string $method HTTP method
     * @param string $url path with an optional query string, e.g. /api/v1/issue?query=...
     * @param array|null $body the JSON body, decoded; null for none
     * @param string $authorization the Authorization header, e.g. "Bearer abc"
     * @return SubResult
     */
    public function run(string $method, string $url, ?array $body, string $authorization): SubResult
    {
        $started = microtime(true);
        $request = SubRequest::fromUrl($method, $url, $body, $authorization);
        $request->setDI($this->di);
        $response = new Response();
        $response->setDI($this->di);
        $scope = new SubRequestScope($this->di);

        try {
            $scope->enter($request, [
                'request' => $request,
                'response' => $response,
                'router' => ($this->routerFactory)(),
                'dispatcher' => $this->newDispatcher($scope),
            ]);
            $response = $this->dispatch($request->path());
        } catch (\Throwable $e) {
            $response = $this->errorResponse($e, $response);
        } finally {
            $scope->leave();
        }

        return SubResult::fromResponse($response, (microtime(true) - $started) * 1000);
    }

    /**
     * Dispatch the path through the router and controller, sending nothing.
     *
     * @param string $path
     * @return \Phalcon\Http\ResponseInterface
     */
    private function dispatch(string $path)
    {
        $application = new Application($this->di);
        $application->useImplicitView(false);
        $application->sendHeadersOnHandleRequest(false);
        $application->sendCookiesOnHandleRequest(false);

        $result = $application->handle($path);

        return $result instanceof \Phalcon\Http\ResponseInterface ? $result : $this->di->getShared('response');
    }

    /**
     * A dispatcher that drops each controller from the DI before building it,
     * so a controller the container already holds is not reused without its
     * initialize() (authentication and ACL) running for this call.
     *
     * @param SubRequestScope $scope
     * @return Dispatcher
     */
    private function newDispatcher(SubRequestScope $scope): Dispatcher
    {
        $eventsManager = new EventsManager();
        $eventsManager->attach(
            'dispatch:beforeDispatch',
            static function ($event, Dispatcher $dispatcher) use ($scope) {
                $scope->evictHandler($dispatcher->getHandlerClass());
            }
        );

        $dispatcher = new Dispatcher();
        $dispatcher->setDI($this->di);
        $dispatcher->setEventsManager($eventsManager);

        return $dispatcher;
    }

    /**
     * The response a separate HTTP call would have got for the exception:
     * Gaia exceptions build their own, Phalcon ones go through PhalconError,
     * anything else is a generic 500. No stack trace reaches the body.
     *
     * @param \Throwable $e
     * @param Response $response the call's response to build on
     * @return Response
     */
    private function errorResponse(\Throwable $e, Response $response): Response
    {
        global $logger;

        if (method_exists($e, 'toResponse')) {
            return $e->toResponse($response);
        }

        if ($e instanceof \Phalcon\Exception) {
            $error = new PhalconError($e, ($GLOBALS['settings']['application']['debug'] ?? false) === true);
            if (isset($logger)) {
                $error->isNotFound()
                    ? $logger->notice('SubRequest PhalconException: ' . $e->getMessage())
                    : $logger->error('SubRequest PhalconException: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
            $response->setStatusCode($error->statusCode(), $error->isNotFound() ? 'Not Found' : 'Internal Server Error');
            $response->setJsonContent($error->body());
            return $response;
        }

        if (isset($logger)) {
            $logger->error('SubRequest ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
        $response->setStatusCode(500, 'Internal Server Error');
        $response->setJsonContent([
            'error' => 'internalServerError',
            'error_description' => 'An internal server error occurred',
        ]);

        return $response;
    }
}
