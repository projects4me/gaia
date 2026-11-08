<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

/**
 * The Acl of a request made with a service token (D-066): deny by default,
 * apart from RBAC roles and the resolution mode.
 *
 * A service has no user, so no role or permission row applies to it. An
 * action resource (`agent.get`) is allowed only when the model opted in for
 * that action and the client's kind (`ServiceAccess`) **and** the token's
 * scope covers it; a token whose scope names no permission covers nothing.
 * Authorization groups must be open to the service in the same way. Fields of
 * a resource the service may read are readable (secure fields are still
 * masked by the controller). Every denial is a 401.
 *
 * Membership record scopes do not apply (a service is a member of nothing);
 * a resource's optional service `filter` limits the rows instead.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceAcl extends Acl
{
    /**
     * Error a service request gets for anything it may not reach.
     */
    public const ACCESS_DENIED_ERROR = 'Access Denied';

    /**
     * The service client's kind, e.g. runtime.
     *
     * @var string
     */
    protected $kind;

    /**
     * The resources' service opt-ins.
     *
     * @var ServiceAccess
     */
    protected $serviceAccess;

    /**
     * @param \Phalcon\Di\FactoryDefault $di
     * @param string                     $kind          The service client's kind.
     * @param ServiceAccess|null         $serviceAccess Read from the metadata manager when omitted.
     */
    public function __construct(\Phalcon\Di\FactoryDefault $di, $kind, ServiceAccess $serviceAccess = null)
    {
        // The resolution mode governs role permissions, which never apply here:
        // keep whatever mode is current instead of resolving or changing it.
        parent::__construct($di, self::$resolutionMode);
        $this->kind = (string) $kind;
        $this->serviceAccess = $serviceAccess ?? new ServiceAccess($di->get('metaManager'));
    }

    /**
     * No user, so no permissions to load.
     *
     * @param  string|null $userId
     * @return void
     */
    protected function loadPermissions($userId)
    {
        $this->userId = null;
        $this->accessibleProjectIds = [];
    }

    /**
     * A service token must carry a scope that covers the resource; an unscoped
     * token reaches nothing.
     *
     * @param  string $resourceName
     * @return bool
     */
    protected function isTokenScopeCovering($resourceName)
    {
        return $this->tokenScope !== null
            && $this->tokenScope->isScoped()
            && $this->tokenScope->covers($resourceName);
    }

    /**
     * Whether the model opted in for the action and this service kind.
     * Field resources (`agent.name.get`) follow their module's action.
     *
     * @param  string $resourceName `{module}.{action}` or `{module}.{field}.{action}`
     * @return bool
     */
    protected function isResourceAllowed($resourceName)
    {
        $parts = explode('.', (string) $resourceName);
        if (count($parts) < 2 || $parts[0] === '') {
            return false;
        }

        return $this->serviceAccess->allows(ucfirst($parts[0]), end($parts), $this->kind);
    }

    /**
     * Only stored role grants count as explicit field writes; a service has none.
     *
     * @param  string $modelName
     * @param  string $field
     * @return bool
     */
    protected function isFieldWriteExplicitlyAllowed($modelName, $field)
    {
        return false;
    }

    /**
     * Membership record scopes (project.get 0/1/2) are for users.
     *
     * @param  string $resourceName
     * @return bool
     */
    public function isScopedResource($resourceName)
    {
        return false;
    }

    /**
     * Membership record scopes do not apply to services.
     *
     * @param  string $modelName
     * @return array|null
     */
    protected function resolveRecordScopeBinding($modelName)
    {
        return null;
    }

    /**
     * Limit the rows to the model's service filter, if it declares one.
     *
     * @param  string $modelName
     * @param  array  $params
     * @return void
     */
    public function applyRecordScope($modelName, array &$params)
    {
        $filter = $this->serviceAccess->recordFilter($modelName);
        if ($filter !== null) {
            $params['aclWhere'] = '(' . $filter . ')';
        }
    }

    /**
     * Deny a record outside the model's service filter (or missing, when the
     * model has a filter).
     *
     * @param  string $modelName
     * @param  string $recordId
     * @return void
     * @throws \Gaia\Exception\UnAuthorized
     */
    public function assertRecordIdInScope($modelName, $recordId)
    {
        $filter = $this->serviceAccess->recordFilter($modelName);
        if ($filter === null) {
            return;
        }

        if (!$this->recordMatchesFilter($modelName, (string) $recordId, $filter)) {
            $this->denyAccess("Access denied: {$modelName} {$recordId} is outside the service filter");
        }
    }

    /**
     * Whether the record exists and matches the filter.
     *
     * @param  string $modelName
     * @param  string $recordId
     * @param  string $filter
     * @return bool
     */
    protected function recordMatchesFilter($modelName, $recordId, $filter)
    {
        $row = $this->di->get('modelsManager')->createBuilder()
            ->columns(['COUNT(*) AS matches'])
            ->from([$modelName => 'Gaia\\MVC\\Models\\' . $modelName])
            ->where("{$modelName}.id = :recordId:", ['recordId' => $recordId])
            ->andWhere('(' . $filter . ')')
            ->getQuery()
            ->getSingleResult();

        return $row !== null && (int) $row->matches > 0;
    }

    /**
     * Log the detail and answer 401: a service token is either allowed
     * through an opt-in or not authorized at all.
     *
     * @param  string $detail
     * @return void
     * @throws \Gaia\Exception\UnAuthorized
     */
    protected function denyAccess($detail)
    {
        global $logger;

        if (isset($logger)) {
            $logger->warning('Service request: ' . $detail);
        }

        throw new \Gaia\Exception\UnAuthorized(self::ACCESS_DENIED_ERROR);
    }
}
