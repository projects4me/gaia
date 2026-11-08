<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

/**
 * Which resources a service token may reach, as the resources declare it in
 * their model ACL metadata (D-066). A resource opts in with:
 *
 * ```php
 * 'acl' => array(
 *     'service' => array(
 *         'actions' => array('get'),          // actions services may perform
 *         'kinds' => array('runtime'),        // service kinds (config/serviceclients.php)
 *         'filter' => 'Agent.delegable = 1',  // optional PHQL condition on the rows
 *         'fields' => array('rotateSecret'),  // fields services may write
 *         'scopes' => array('update' => 'serviceclient.rotate'), // dedicated scope per action
 *     ),
 * ),
 * ```
 *
 * Nothing is reachable unless declared: a resource without `service`, an
 * action or kind not listed, or an action outside `ACTIONS` is denied.
 *
 * The filter may name the calling client with the bound placeholder
 * `:serviceClientId:` (`CLIENT_PARAM`), e.g. a service's own record. A write
 * action is open only with a filter, and only the listed `fields` may be
 * written (D-067). An action listed in `scopes` is granted only by a token
 * carrying exactly that scope word, not by a pattern of the action resource.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceAccess
{
    /**
     * Actions a resource may open to services. Create and delete are not
     * among them. An update must be limited by a filter, and a service request
     * has no current user while the write behaviours (createdUser,
     * modifiedUser, audit) stamp one: a resource that opens `update` performs
     * the write itself (as `ServiceclientController` rotates a secret).
     *
     * @var string[]
     */
    const ACTIONS = ['get', 'update'];

    /**
     * Actions that change data, open only together with a filter.
     *
     * @var string[]
     */
    const WRITE_ACTIONS = ['update'];

    /**
     * Name of the bound placeholder a filter uses for the calling client's id.
     */
    const CLIENT_PARAM = 'serviceClientId';

    /**
     * Source of model metadata (`getModelMeta($modelName)`).
     *
     * @var object
     */
    protected $metaManager;

    /**
     * @param object $metaManager The metadata manager (`metaManager` service).
     */
    public function __construct($metaManager)
    {
        $this->metaManager = $metaManager;
    }

    /**
     * Whether a service of the kind may perform the action on the model.
     *
     * @param  string $modelName e.g. Agent
     * @param  string $action    e.g. get
     * @param  string $kind      The service client's kind, e.g. runtime
     * @return bool
     */
    public function allows($modelName, $action, $kind)
    {
        $service = $this->serviceMeta($modelName);
        if ($service === [] || !in_array($action, self::ACTIONS, true)) {
            return false;
        }

        if (in_array($action, self::WRITE_ACTIONS, true) && $this->recordFilter($modelName) === null) {
            return false;
        }

        return in_array($action, $this->stringList($service, 'actions'), true)
            && in_array((string) $kind, $this->stringList($service, 'kinds'), true);
    }

    /**
     * Whether a service may write the field (fields are closed unless listed).
     *
     * @param  string $modelName
     * @param  string $field
     * @return bool
     */
    public function allowsFieldWrite($modelName, $field)
    {
        return in_array((string) $field, $this->stringList($this->serviceMeta($modelName), 'fields'), true);
    }

    /**
     * The scope word that alone grants the action, if the resource names one.
     *
     * @param  string $modelName
     * @param  string $action
     * @return string|null
     */
    public function requiredScope($modelName, $action)
    {
        $service = $this->serviceMeta($modelName);
        $scopes = isset($service['scopes']) && is_array($service['scopes']) ? $service['scopes'] : [];
        $scope = $scopes[$action] ?? null;

        return is_string($scope) && trim($scope) !== '' ? $scope : null;
    }

    /**
     * The PHQL condition that limits the rows services see, if any.
     *
     * @param  string $modelName
     * @return string|null
     */
    public function recordFilter($modelName)
    {
        $service = $this->serviceMeta($modelName);
        if (!isset($service['filter']) || !is_string($service['filter']) || trim($service['filter']) === '') {
            return null;
        }

        return $service['filter'];
    }

    /**
     * The model's `acl.service` metadata; empty when it did not opt in or is unknown.
     *
     * @param  string $modelName
     * @return array
     */
    protected function serviceMeta($modelName)
    {
        try {
            $metadata = $this->metaManager->getModelMeta($modelName);
        } catch (\Throwable $e) {
            return [];
        }

        $service = $metadata['acl']['service'] ?? null;
        if (is_object($service) && method_exists($service, 'toArray')) {
            $service = $service->toArray();
        }

        return is_array($service) ? $service : [];
    }

    /**
     * A list of strings from the service metadata.
     *
     * @param  array  $service
     * @param  string $key
     * @return string[]
     */
    protected function stringList(array $service, $key)
    {
        $values = isset($service[$key]) && is_array($service[$key]) ? $service[$key] : [];
        return array_values(array_filter($values, 'is_string'));
    }
}
