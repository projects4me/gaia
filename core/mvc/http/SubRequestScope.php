<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Http;

use Phalcon\Di\DiInterface;

/**
 * Saves the outer request's per-request state, lets one sub-request replace
 * it, and puts it back.
 *
 * The state is what the REST stack reads as "the current request": the DI
 * request, response, router, dispatcher and acl services, the superglobals
 * (Phalcon's getMethod(), getURI() and getHeader() are final and read
 * $_SERVER) and $GLOBALS['currentUser']. Controllers the sub-request
 * dispatched are dropped from the DI so the next call builds, initializes and
 * authorizes a fresh instance.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia
 * @category Http
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SubRequestScope
{
    /**
     * DI services that belong to one request.
     *
     * @var string[]
     */
    private const SERVICES = ['request', 'response', 'router', 'dispatcher', 'acl'];

    /**
     * @var DiInterface
     */
    private $di;

    /**
     * Outer service instances by name; null when the service was not registered.
     *
     * @var array
     */
    private $services = [];

    /**
     * @var array
     */
    private $superglobals = [];

    /**
     * @var bool
     */
    private $hadCurrentUser = false;

    /**
     * Whether enter() saved the outer state and started changing it.
     *
     * @var bool
     */
    private $entered = false;

    /**
     * @var mixed
     */
    private $currentUser;

    /**
     * Controller classes dispatched inside the scope => the outer service or
     * controller instance to restore (null for none).
     *
     * @var array<string, \Phalcon\Di\ServiceInterface|object|null>
     */
    private $handlers = [];

    /**
     * @param DiInterface $di
     */
    public function __construct(DiInterface $di)
    {
        $this->di = $di;
    }

    /**
     * Save the outer state and install the sub-request's.
     *
     * @param SubRequest $request
     * @param array $services name => instance for request, response, router and dispatcher
     * @return void
     */
    public function enter(SubRequest $request, array $services): void
    {
        $saved = [];
        foreach (self::SERVICES as $name) {
            $saved[$name] = $this->di->has($name) ? $this->di->getShared($name) : null;
        }
        $this->services = $saved;
        $this->superglobals = [
            '_SERVER' => $_SERVER ?? [],
            '_GET' => $_GET ?? [],
            '_POST' => $_POST ?? [],
            '_REQUEST' => $_REQUEST ?? [],
            '_FILES' => $_FILES ?? [],
            '_COOKIE' => $_COOKIE ?? [],
        ];
        $this->hadCurrentUser = array_key_exists('currentUser', $GLOBALS);
        $this->currentUser = $this->hadCurrentUser ? $GLOBALS['currentUser'] : null;
        $this->entered = true;

        foreach ($request->superglobals($this->superglobals['_SERVER']) as $name => $value) {
            $this->assignSuperglobal($name, $value);
        }
        $GLOBALS['currentUser'] = null;
        $this->di->remove('acl');
        foreach ($services as $name => $instance) {
            $this->replaceService($name, $instance);
        }
    }

    /**
     * Drop the controller about to be dispatched, so it is built fresh and its
     * initialize() (which authenticates and authorizes) runs for this call.
     *
     * @param string $handlerClass
     * @return void
     */
    public function evictHandler(string $handlerClass): void
    {
        if (!array_key_exists($handlerClass, $this->handlers)) {
            // The outer request may be running this controller: keep its
            // service (with the instance it resolved) to put back in leave().
            $this->handlers[$handlerClass] = $this->di->has($handlerClass)
                ? $this->di->getService($handlerClass)
                : $this->outerController($handlerClass);
        }
        $this->di->remove($handlerClass);
    }

    /**
     * The outer dispatcher's controller when it is of this class. The
     * dispatcher builds controllers with getShared(), which caches an
     * unregistered class without registering a service (has() is false), so
     * the instance itself is what leave() must put back.
     *
     * @param string $handlerClass
     * @return object|null
     */
    private function outerController(string $handlerClass)
    {
        $dispatcher = $this->services['dispatcher'] ?? null;
        if (!$dispatcher instanceof \Phalcon\Dispatcher\DispatcherInterface || !method_exists($dispatcher, 'getActiveController')) {
            return null;
        }
        $controller = $dispatcher->getActiveController();

        return $controller instanceof $handlerClass ? $controller : null;
    }

    /**
     * Put the outer state back, whatever the sub-request did. Does nothing
     * when enter() failed before it changed anything.
     *
     * @return void
     */
    public function leave(): void
    {
        if (!$this->entered) {
            return;
        }
        $this->entered = false;
        foreach ($this->handlers as $handlerClass => $outerService) {
            $this->di->remove($handlerClass);
            if ($outerService instanceof \Phalcon\Di\ServiceInterface) {
                $this->di->setService($handlerClass, $outerService);
            } elseif ($outerService !== null) {
                $this->di->setShared($handlerClass, $outerService);
            }
        }
        foreach ($this->services as $name => $instance) {
            $this->replaceService($name, $instance);
        }
        foreach ($this->superglobals as $name => $value) {
            $this->assignSuperglobal($name, $value);
        }
        if ($this->hadCurrentUser) {
            $GLOBALS['currentUser'] = $this->currentUser;
        } else {
            unset($GLOBALS['currentUser']);
        }
        $this->services = [];
        $this->superglobals = [];
        $this->handlers = [];
    }

    /**
     * Register an instance as a shared service, or remove the service for null.
     *
     * Phalcon keeps a resolved shared instance after setShared(), so the old
     * one is removed first.
     *
     * @param string $name
     * @param object|null $instance
     * @return void
     */
    private function replaceService(string $name, $instance): void
    {
        $this->di->remove($name);
        if ($instance !== null) {
            $this->di->setShared($name, $instance);
        }
    }

    /**
     * Assign a superglobal directly ($_SERVER is only reachable through
     * $GLOBALS once the engine has materialized it).
     *
     * @param string $name
     * @param array $value
     * @return void
     */
    private function assignSuperglobal(string $name, array $value): void
    {
        switch ($name) {
            case '_SERVER':
                $_SERVER = $value;
                break;
            case '_GET':
                $_GET = $value;
                break;
            case '_POST':
                $_POST = $value;
                break;
            case '_REQUEST':
                $_REQUEST = $value;
                break;
            case '_FILES':
                $_FILES = $value;
                break;
            case '_COOKIE':
                $_COOKIE = $value;
                break;
        }
    }
}
