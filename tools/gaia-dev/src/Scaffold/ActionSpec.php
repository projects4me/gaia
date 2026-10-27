<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * What `gaia-dev scaffold action <Controller> <verb>` was asked to write: a thin override of one standard REST
 * action in an existing controller. Gaia has no custom routes, so the override reuses the verb's route and
 * its permission.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ActionSpec
{
    /**
     * Verb => [controller method, HTTP method, permission verb, whether the route addresses one record].
     */
    public const VERBS = [
        'list' => ['listAction', 'GET', 'get', false],
        'get' => ['getAction', 'GET', 'get', true],
        'post' => ['postAction', 'POST', 'create', false],
        'patch' => ['patchAction', 'PATCH', 'update', true],
        'delete' => ['deleteAction', 'DELETE', 'delete', true],
    ];

    /**
     * Tag in the action's docblock that `check action` looks for.
     */
    public const MARKER = '@gaia-dev action';

    public const CONTROLLERS = 'app/api/v1/controllers';

    /**
     * @var string model name, e.g. Issuestatus
     */
    public $model;

    /**
     * @var string list, get, post, patch or delete
     */
    public $verb;

    /**
     * @var string the domain class the rules belong in, e.g. IssuestatusRules
     */
    public $domain;

    /**
     * @param string $controller Issuestatus or IssuestatusController
     * @param string $verb list, get, post, patch or delete
     * @param array $options parsed command line options
     * @throws ScaffoldException
     */
    public function __construct(string $controller, string $verb, array $options)
    {
        $model = preg_replace('/Controller$/', '', $controller);
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $model)) {
            throw new ScaffoldException("Controller '{$controller}' must look like Issuestatus");
        }
        if (!isset(self::VERBS[$verb])) {
            throw new ScaffoldException("Unknown verb '{$verb}'; use " . implode(', ', array_keys(self::VERBS)) . '. Gaia has no custom actions: override a standard one');
        }
        $this->model = $model;
        $this->verb = $verb;
        $this->domain = isset($options['domain']) ? (string) $options['domain'] : $model . 'Rules';
        if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $this->domain)) {
            throw new ScaffoldException("--domain '{$this->domain}' must look like IssuestatusRules");
        }
    }

    /**
     * @return string the REST resource name, e.g. issuestatus
     */
    public function resource(): string
    {
        return strtolower($this->model);
    }

    /**
     * @return string the controller method, e.g. postAction
     */
    public function action(): string
    {
        return self::VERBS[$this->verb][0];
    }

    /**
     * @return string the HTTP method of the verb's standard route
     */
    public function method(): string
    {
        return self::VERBS[$this->verb][1];
    }

    /**
     * @return string the permission the standard route checks, e.g. issuestatus.create
     */
    public function permission(): string
    {
        return $this->resource() . '.' . self::VERBS[$this->verb][2];
    }

    /**
     * @return string the verb's standard route
     */
    public function path(): string
    {
        return '/api/v1/' . $this->resource() . (self::VERBS[$this->verb][3] ? '/{id}' : '');
    }

    /**
     * @return string repository relative path of the controller
     */
    public function controllerPath(): string
    {
        return self::CONTROLLERS . '/' . $this->model . 'Controller.php';
    }

    /**
     * @return string the domain class to write next
     */
    public function domainClass(): string
    {
        return 'Gaia\\Libraries\\Utils\\' . $this->domain;
    }
}
