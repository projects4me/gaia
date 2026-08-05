<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\MVC\REST\Controllers\AclAdminController;
use Gaia\Libraries\Utils\Util;
use Gaia\Libraries\Security\AclMapCatalog;
use Gaia\MVC\Models\Permission;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Permissions Controller
 *
 * Module actions use binary allowed (0/1/'').
 * Field ACL is administered as one mode resource per field
 * (`issue.subject` + allowed none|read|write|''); storage expands to
 * get/create/update triples internally.
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
     * This method returns all of the available and the applied permissions of the system.
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
     * Prepare default permissions for ACL-allowed models and their field modes.
     * Relationships are omitted — access is governed by the related model itself.
     *
     * @method getDefaultPermissions
     * @return array
     */
    protected function getDefaultPermissions()
    {
        global $settings;
        $moduleActions = $settings['system']['acl']['moduleActions']->toArray();
        $moduleFields = isset($settings['system']['acl']['moduleFields'])
            ? $settings['system']['acl']['moduleFields']->toArray()
            : [];
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

        foreach ($moduleFields as $moduleDefinition) {
            if (empty($moduleDefinition['fields']) || !is_array($moduleDefinition['fields'])) {
                continue;
            }
            foreach ($moduleDefinition['fields'] as $fieldDefinition) {
                if (empty($fieldDefinition['resourceName'])) {
                    continue;
                }
                $permission = $permissionInterface;
                $permission['attributes']['resourceName'] = $fieldDefinition['resourceName'];
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
     * Applied permissions for a role: module actions as stored, field modes collapsed
     * from get/create/update triples (triples are never returned to the client).
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

        $rows = $this->findPermissionRows($roleId);
        $fieldFlagsByModeResource = [];
        $fieldRowIds = [];

        foreach ($rows as $row) {
            $resourceName = isset($row['resourceName']) ? $row['resourceName'] : '';
            if (AclMapCatalog::isFieldActionResource($resourceName)) {
                $parts = explode('.', $resourceName);
                $modeResource = $parts[0] . '.' . $parts[1];
                $action = $parts[2];
                if (!isset($fieldFlagsByModeResource[$modeResource])) {
                    $fieldFlagsByModeResource[$modeResource] = [
                        'get' => null,
                        'create' => null,
                        'update' => null,
                    ];
                    $fieldRowIds[$modeResource] = isset($row['id']) ? $row['id'] : create_guid();
                }
                $fieldFlagsByModeResource[$modeResource][$action] = $row['allowed'];
                if ($action === 'get' && !empty($row['id'])) {
                    $fieldRowIds[$modeResource] = $row['id'];
                }
                continue;
            }

            $appliedPermissions['data'][] = [
                'type' => 'Permission',
                'id' => isset($row['id']) ? $row['id'] : create_guid(),
                'attributes' => [
                    'resourceName' => $resourceName,
                    'allowed' => $row['allowed'],
                    'roleId' => $row['roleId'],
                ],
            ];
        }

        foreach ($fieldFlagsByModeResource as $modeResource => $flags) {
            $hasAnyRow = ($flags['get'] !== null || $flags['create'] !== null || $flags['update'] !== null);
            $allowed = $hasAnyRow
                ? Permission::deriveFieldAccessMode($flags)
                : '';

            $appliedPermissions['data'][] = [
                'type' => 'Permission',
                'id' => $fieldRowIds[$modeResource],
                'attributes' => [
                    'resourceName' => $modeResource,
                    'allowed' => $allowed,
                    'roleId' => $roleId,
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
            if ($this->isFieldModeResourceName($attributes['resourceName'])) {
                return $this->saveFieldModePermission($attributes);
            }
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
        $attributes = $requestData['data']['attributes'];

        if ($this->passPreReqs($attributes) === true) {
            if ($this->isFieldModeResourceName($attributes['resourceName'])) {
                return $this->saveFieldModePermission($attributes);
            }
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

        if (AclMapCatalog::isFieldActionResource($resourceName)) {
            throw new \Gaia\Exception\Permission(
                "Field action permissions cannot be set directly",
                null,
                null,
                [
                    'suggestion' => 'Use the field mode resource (e.g. issue.subject) with allowed none|read|write'
                ]
            );
        }

        if ($this->canApplyAcl($resourceName)) {
            if ($this->isFieldModeResourceName($resourceName)) {
                $this->passFieldModeFlagChecks($values);
            } else {
                $this->passBinaryFlagChecks($values);
            }

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
     * Validate field-mode allowed values.
     *
     * @param  array $values
     * @return bool
     */
    private function passFieldModeFlagChecks($values)
    {
        $value = isset($values['allowed']) ? (string) $values['allowed'] : '';
        $allowedValues = array_merge([''], Permission::FIELD_ACCESS_MODES);
        if (!in_array($value, $allowedValues, true)) {
            throw new \Gaia\Exception\Permission(
                "You're not allowed to set {$value}",
                null,
                null,
                [
                    'suggestion' => 'You can only set none, read, write, or leave unset'
                ]
            );
        }

        return true;
    }

    /**
     * Expand a field-mode write into get/create/update rows (or clear them when unset).
     *
     * @param  array $attributes
     * @return \Phalcon\Http\Response
     */
    private function saveFieldModePermission(array $attributes)
    {
        $resourceName = $attributes['resourceName'];
        $roleId = isset($attributes['roleId']) ? $attributes['roleId'] : '';
        $mode = isset($attributes['allowed']) ? (string) $attributes['allowed'] : '';

        if (!$roleId) {
            throw new \Gaia\Exception\Exception("Please specify roleId");
        }

        $parts = explode('.', $resourceName, 2);
        $moduleName = $parts[0];
        $field = $parts[1];

        if ($mode === '') {
            $this->deleteFieldActionRows($roleId, $moduleName, $field);
            $synthetic = $this->buildSyntheticFieldPermission(
                create_guid(),
                $resourceName,
                '',
                $roleId
            );
        } else {
            $flags = Permission::expandFieldAccessMode($mode);
            $primaryId = null;
            foreach (AclMapCatalog::FIELD_ACTIONS as $action) {
                $actionResource = AclMapCatalog::buildFieldActionResourceName(
                    $moduleName,
                    $field,
                    $action
                );
                $rowId = $this->upsertPermissionRow(
                    $roleId,
                    $actionResource,
                    $flags[$action]
                );
                if ($action === 'get') {
                    $primaryId = $rowId;
                }
            }
            $synthetic = $this->buildSyntheticFieldPermission(
                $primaryId ? $primaryId : create_guid(),
                $resourceName,
                $mode,
                $roleId
            );
        }

        $this->response->setStatusCode(200, 'OK');
        // Singular JSON:API resource (same shape as RestController create/update).
        $this->finalData = [
            'data' => $synthetic,
            'meta' => [
                'count' => 1,
            ],
        ];
        return $this->returnResponse($this->finalData);
    }

    /**
     * Upsert a single permission row by roleId + resourceName.
     *
     * @param  string $roleId
     * @param  string $resourceName
     * @param  string $allowed
     * @return string Permission id
     */
    private function upsertPermissionRow($roleId, $resourceName, $allowed)
    {
        $model = Permission::findFirst([
            'conditions' => 'roleId = :roleId: AND resourceName = :resourceName:',
            'bind' => [
                'roleId' => $roleId,
                'resourceName' => $resourceName,
            ],
        ]);

        if (!$model) {
            $model = new Permission();
            $model->id = create_guid();
            $model->roleId = $roleId;
            $model->resourceName = $resourceName;
        }

        $model->allowed = $allowed;
        if (!$model->save()) {
            $errors = [];
            foreach ($model->getMessages() as $message) {
                $errors[] = $message->getMessage();
            }
            throw new \Gaia\Exception\Exception(
                'Failed to save field permission: ' . implode(', ', $errors)
            );
        }

        return $model->id;
    }

    /**
     * Delete all field-action triples for a role + field.
     *
     * @param  string $roleId
     * @param  string $moduleName
     * @param  string $field
     * @return void
     */
    private function deleteFieldActionRows($roleId, $moduleName, $field)
    {
        foreach (AclMapCatalog::FIELD_ACTIONS as $action) {
            $actionResource = AclMapCatalog::buildFieldActionResourceName(
                $moduleName,
                $field,
                $action
            );
            $model = Permission::findFirst([
                'conditions' => 'roleId = :roleId: AND resourceName = :resourceName:',
                'bind' => [
                    'roleId' => $roleId,
                    'resourceName' => $actionResource,
                ],
            ]);
            if ($model) {
                $model->delete();
            }
        }
    }

    /**
     * @param  string $id
     * @param  string $resourceName
     * @param  string $allowed
     * @param  string $roleId
     * @return array
     */
    private function buildSyntheticFieldPermission($id, $resourceName, $allowed, $roleId)
    {
        return [
            'type' => 'Permission',
            'id' => $id,
            'attributes' => [
                'resourceName' => $resourceName,
                'allowed' => $allowed,
                'roleId' => $roleId,
            ],
        ];
    }

    /**
     * @param  string $resourceName
     * @return bool
     */
    private function isFieldModeResourceName($resourceName)
    {
        return AclMapCatalog::isFieldModeResource($resourceName, $this->getModuleFieldsCatalog());
    }

    /**
     * @return array
     */
    private function getModuleFieldsCatalog()
    {
        global $settings;
        return isset($settings['system']['acl']['moduleFields'])
            ? $settings['system']['acl']['moduleFields']->toArray()
            : [];
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

        if ($this->isFieldModeResourceName($resourceName)) {
            return true;
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
