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
 *     ),
 * ),
 * ```
 *
 * Nothing is reachable unless declared: a resource without `service`, an
 * action or kind not listed, or an action outside `ACTIONS` is denied.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceAccess
{
    /**
     * Actions a resource may open to services. Writes are not among them:
     * a service request has no current user, and the write behaviours
     * (createdUser, modifiedUser, audit) stamp one.
     *
     * @var string[]
     */
    const ACTIONS = ['get'];

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

        return in_array($action, $this->stringList($service, 'actions'), true)
            && in_array((string) $kind, $this->stringList($service, 'kinds'), true);
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
