<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\MVC\REST\Controllers\AclAdminController;
use Gaia\Libraries\Utils\Util;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Permissions Controller
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PermissionController extends AclAdminController
{
    /**
     * This components that this controller uses.
     *
     * @var $uses
     */
    public $uses = ['Permission'];

    /**
     * Project authorization flag
     *
     * @var bool
     */
    protected $projectAuthorization = false;

    /**
     * System level flag
     *
     * @var bool
     */
    protected $systemLevel = true;

    /**
     * This method returns all of the avialable and the applied permissions of the system.
     *
     * @method listAction
     * @return \Phalcon\Http\Response
     */
    public function listAction()
    {
        $defaultPermissions = $this->getDefaultPermissions();
        $appliedPermissions = (($this->getAppliedPermissions()) ?? []);

        // Map appliedPermissions array by 'resourceName' for easier lookup.
        $permissionsMap = [];
        foreach ($appliedPermissions['data'] as $appliedPermission) {
            $requestedResource = $appliedPermission['attributes']['resourceName'];
            $permissionsMap[$requestedResource] = $appliedPermission;
        }

        // Merge applied permission into default against the resource name.
        foreach ($defaultPermissions['data'] as $index => $defaultPermission) {
            $requestedResource = $defaultPermission['attributes']['resourceName'];
            if (isset($permissionsMap[$requestedResource]) === true) {
                $defaultPermissions['data'][$index] = $permissionsMap[$requestedResource];
            }
        }

        $this->finalData = $this->buildHAL($defaultPermissions);

        return $this->returnResponse($this->finalData);
    }

    /**
     * Prepare default permissions for ACL-allowed models and their fields.
     * Relationships are omitted — access is governed by the related model itself.
     *
     * @method getDefaultPermissions
     * @return array
     */
    protected function getDefaultPermissions()
    {
        global $settings;
        $moduleActions = $settings['system']['acl']['moduleActions']->toArray();
        $permissionInterface = $this->getPermissionInterface();
        $permissions = ['data' => []];

        foreach ($moduleActions as $moduleDefinition) {
            foreach ($moduleDefinition['actions'] as $actionDefinition) {
                $permission = $permissionInterface;
                $permission['attributes']['resourceName'] = $actionDefinition['resourceName'];
                $permission['id'] = create_guid();
                $permissions['data'][] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * This method returns permission interface.
     *
     * @method getPermissionInterface
     * @return array
     */
    protected function getPermissionInterface()
    {
        $permissionInterface = ['type' => 'Permission'];
        $permissionInterface['attributes'] = [];
        $permissionInterface['id'] = '';
        $permissionInterface['attributes']['resourceName'] = '';
        $permissionInterface['attributes']['allowed'] = '';
        $permissionInterface['attributes']['roleId'] = '';

        return $permissionInterface;
    }

    /**
     * This method retrieve all of the permissions applied against a role from the database and
     * return that array.
     *
     * @method getAppliedPermissions
     * @return array
     */
    protected function getAppliedPermissions()
    {
        $appliedPermissions = ['data' => []];
        $roleId = $this->request->get('roleId');

        if (!$roleId) {
            return $appliedPermissions;
        }

        foreach ($this->findPermissionRows($roleId) as $row) {
            $appliedPermissions['data'][] = [
                'type' => 'Permission',
                'id' => isset($row['id']) ? $row['id'] : create_guid(),
                'attributes' => [
                    'resourceName' => $row['resourceName'],
                    'allowed' => $row['allowed'],
                    'roleId' => $row['roleId'],
                ],
            ];
        }

        return $appliedPermissions;
    }

    /**
     * This function is used to create permission.
     *
     * @method postAction
     * @return \Phalcon\Http\Response|null
     */
    public function postAction()
    {
        global $logger;
        $logger->debug('+Gaia.core.controllers.permission->postAction');

        $util = new Util();

        $requestData = $util->objectToArray($this->request->getJsonRawBody());
        $attributes = $requestData['data']['attributes'] ?? $requestData;

        if ($this->passPreReqs($attributes) === true) {
            return parent::postAction();
        } else {
            throw new \Gaia\Exception\Exception("Permission cannot be created due to some reasons");
        }

        $logger->debug('-Gaia.core.controllers.permission->postAction');
    }

    /**
     * This function is used to update the permission.
     *
     * @throws \Gaia\Exception\Permission
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        global $logger;
        $logger->debug('+Gaia.core.controllers.permission->patchAction');
        $util = new Util();
        $requestData = $util->objectToArray($this->request->getJsonRawBody());
        $requestData = $requestData['data']['attributes'];

        if ($this->passPreReqs($requestData) === true) {
            return parent::patchAction();
        } else {
            throw new \Gaia\Exception\Permission("Permission cannot be updated due to some reasons");
        }
        $logger->debug('-Gaia.core.controllers.permission->patchAction');
    }

    /**
     * This function verify all of the checks that are required to create a permission.
     *
     * @method passPreReqs
     * @param  array $values Request values
     * @return bool
     */
    private function passPreReqs(&$values)
    {
        // Resource name required.
        $resourceName = $values['resourceName'];
        if (!$resourceName) {
            throw new \Gaia\Exception\Exception("Please specify resource name");
        }

        if ($this->canApplyAcl($resourceName)) {
            $this->passBinaryFlagChecks($values);

            return true;
        }
    }

    /**
     * Validate permission flags are binary allow/deny values (0 or 1).
     *
     * @method passBinaryFlagChecks
     * @param  array $values          Array containing values of the request.
     * @return bool
     */
    private function passBinaryFlagChecks($values)
    {
        $allowedValues = ['0', '1', ''];
        $value = isset($values['allowed']) ? (string) $values['allowed'] : '';
        if (!in_array($value, $allowedValues, true)) {
            throw new \Gaia\Exception\Permission(
                "You're not allowed to set {$value}",
                null,
                null,
                [
                    'suggestion' => 'You can only set 0 (none) or 1 (allow)'
                ]
            );
        }

        return true;
    }

    /**
     * This function is used to check whether acl should applied to the given resource or not.
     *
     * @method canApplyAcl
     * @param  string $resourceName
     * @return boolean
     */
    private function canApplyAcl($resourceName)
    {
        global $settings;
        $moduleActions = $settings['system']['acl']['moduleActions']->toArray();
        foreach ($moduleActions as $moduleDefinition) {
            foreach ($moduleDefinition['actions'] as $actionDefinition) {
                if ($actionDefinition['resourceName'] === $resourceName) {
                    return true;
                }
            }
        }

        throw new \Gaia\Exception\Exception("Permission cannot be created for {$resourceName}");
    }

    /**
     * Query permission rows for a role.
     *
     * Permissions are always applied to roles, never directly to users.
     *
     * @param  string $roleId
     * @return array
     */
    private function findPermissionRows($roleId)
    {
        $queryBuilder = $this->di->get('modelsManager')->createBuilder()
            ->columns([
                'Permission.id as id',
                'Permission.roleId as roleId',
                'Permission.resourceName as resourceName',
                'Permission.allowed as allowed',
            ])
            ->from(['Permission' => 'Gaia\\MVC\\Models\\Permission'])
            ->where('Permission.roleId = :roleId:', ['roleId' => $roleId]);

        return $queryBuilder->getQuery()->execute()->toArray();
    }
}
