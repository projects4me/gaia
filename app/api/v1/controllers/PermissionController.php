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
        $path = APP_PATH . '/app/metadata/model';
        $permissions = ['data' => []];

        global $settings;
        $models = $settings['models'];

        $permissionInterface = $this->getPermissionInterface($path);
        $permissionIndex = 0;

        foreach ($models as $modelName) {
            if (!$this->canApplyModelAcl($modelName)) {
                continue;
            }

            $data = $this->di->get('metaManager')->getModelMeta($modelName);

            $this->addPermissions($permissionIndex, $modelName, [$modelName], $permissions, $permissionInterface, false, $data);

            $requestedFields = array_keys($data['fields'] ?? []);
            $this->addPermissions($permissionIndex, $modelName, $requestedFields, $permissions, $permissionInterface, true, $data);
        }

        return $permissions;
    }

    /**
     * Create permission entries for the given field/model list.
     *
     * @method addPermissions
     * @param  int    $permissionIndex
     * @param  string $modelName
     * @param  array  $requestedFields
     * @param  array  $permissions
     * @param  array  $permissionInterface
     * @param  bool   $addPrefix
     * @param  array  $metadata
     * @return void
     */
    protected function addPermissions(&$permissionIndex, $modelName, $requestedFields, &$permissions, $permissionInterface, $addPrefix, $metadata)
    {
        $modelFields = $requestedFields;
        if ($addPrefix === true) {
            $modelFields = array_map(
                function ($requestedField) use ($modelName) {
                    return "{$modelName}.{$requestedField}";
                },
                $requestedFields
            );
        }

        foreach ($modelFields as $modelField) {
            $parts = explode('.', $modelField);
            $fieldName = $parts[1] ?? $modelField;

            if ($this->canApplyFieldAcl($fieldName, $metadata)) {
                $permissionIndex++;
                $entry = $permissionInterface;
                $entry['attributes']['resourceName'] = $modelField;
                $entry['attributes']['resourceId'] = "new_resource_{$permissionIndex}";
                $entry['id'] = create_guid();
                $permissions['data'][] = $entry;
            }
        }
    }

    /**
     * This method returns permission interface.
     *
     * @method getPermissionInterface
     * @param  string $path Path of the permission model.
     * @return array
     */
    protected function getPermissionInterface($path)
    {
        $permissionInterface = ['type' => 'Permission'];
        $permissionInterface['attributes'] = [];
        $permissionInterface['id'] = '';
        $permissionModel = $this->di->get('fileHandler')->readFile("{$path}/Permission.php");

        $permissionFields = array_keys($permissionModel['Permission']['fields']);
        $notRequiredFields = ['controllerId', 'id'];

        $requestedFields = array_diff($permissionFields, $notRequiredFields);
        foreach ($requestedFields as $requestedField) {
            $permissionInterface['attributes'][$requestedField] = '';
        }

        return $permissionInterface;
    }

    /**
     * This method retrieve all of the permissions applied against the role or a user from the database and
     * return that array.
     *
     * @method getAppliedPermissions
     * @return array
     */
    protected function getAppliedPermissions()
    {
        $allowedQueryParams = ['roleId', 'userId'];
        $queryParamsRequested = [];
        $appliedPermissions = ['data' => []];
        $maps = [
            'rels' => [
                'roleId' => ['resource'],
                'userId' => ['aclController', 'resource']
            ],
            'fields' => [
                'roleId' => 'resource.entity as resourceName',
                'userId' => 'aclController.relatedId as userId, resource.entity as resourceName'
            ],
            'query' => [
                'roleId' => 'Permission.roleId',
                'userId' => 'aclController.relatedId'
            ]
        ];

        foreach ($allowedQueryParams as $allowedQueryParam) {
            ($this->request->get($allowedQueryParam))
                && ($queryParamsRequested[$allowedQueryParam] = $this->request->get($allowedQueryParam));
        }

        foreach ($queryParamsRequested as $queryParam => $value) {
            $query = "(({$maps['query'][$queryParam]} : {$value}))";

            $requestedFields = ["Permission.*"];
            $requestedFields[] = $maps['fields'][$queryParam];

            $params = [
                'where' => $query,
                'rels' => $maps['rels'][$queryParam],
                'fields' => $requestedFields,
            ];

            $permissionModel = new $this->modelName();
            $data = $permissionModel->readAll($params);
            $dataArray = $this->extractData($data, $params, true);
            $appliedPermissions['data'] = array_merge($appliedPermissions['data'], $dataArray['data']);
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

        /*
         * Fetch resource if not attached to permission and create permission. Below conditional statement
         * will work in case when post request is generated from frontend when a default permission, which is not
         * inside db, is updated.
         */

        if (isset($requestData['data']['attributes']['resourceId']) && str_contains($requestData['data']['attributes']['resourceId'], 'new') === true) {
            // All of the resources should be available inside the database.
            $requestData = $requestData['data']['attributes'];
        }

        if ($this->passPreReqs($requestData) === true) {
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
            // Get permission flags from configurations.
            global $settings;
            $permissionFlags = $settings['system']['acl']['permissionFlags'];

            // Get only required permission flags.
            $permissionFlags = array_intersect($permissionFlags->toArray(), array_keys($values));

            $this->passBinaryFlagChecks($permissionFlags, $values);

            return true;
        }
    }

    /**
     * Validate permission flags are binary allow/deny values (0 or 1).
     *
     * @method passBinaryFlagChecks
     * @param  array $permissionFlags Array of permission flags.
     * @param  array $values          Array containing values of the request.
     * @return bool
     */
    private function passBinaryFlagChecks($permissionFlags, $values)
    {
        $allowedValues = ['0', '1', ''];

        foreach ($permissionFlags as $flag) {
            if (!isset($values[$flag])) {
                continue;
            }

            $value = (string) $values[$flag];
            if (!in_array($value, $allowedValues, true)) {
                throw new \Gaia\Exception\Permission(
                    "You're not allowed to set {$values[$flag]}",
                    null,
                    null,
                    [
                        'suggestion' => 'You can only set 0 (none) or 1 (allow)'
                    ]
                );
            }
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
        $actionsMap = [
            "post" => "created",
            "patch" => "updated"
        ];

        $modelName = $resourceName;
        $fieldName = null;

        if (str_contains($resourceName, ".")) {
            list($modelName, $fieldName) = explode(".", $resourceName, 2);
        }

        $metadata = $this->di->get('metaManager')->getModelMeta($modelName);
        $action = $actionsMap[$this->actionName] ?? 'saved';

        if ($fieldName && !$this->canApplyFieldAcl($fieldName, $metadata)) {
            throw new \Gaia\Exception\Exception("Permission cannot " . $action);
        }

        if (!$this->canApplyModelAcl($modelName)) {
            throw new \Gaia\Exception\Exception("Permission cannot " . $action);
        }

        return true;
    }

    /**
     * Checks that whether we can apply acl on the model or not.
     *
     * @method canApplyModelAcl
     * @param  string $modelName
     * @return boolean
     */
    private function canApplyModelAcl($modelName)
    {
        $isAllowed = true;

        $modelNamespace = "\\Gaia\\MVC\\Models\\$modelName";

        if (class_exists($modelNamespace)) {
            $model = new $modelNamespace();
            $isAllowed = $model->isAclAllowed();
        } else {
            throw new \Gaia\Exception\Exception("Model not found");
        }

        return $isAllowed;
    }

    /**
     * Checks whether ACL can be applied on a field (skips identifiers / linkedTo / acl:false).
     *
     * @method canApplyFieldAcl
     * @param  string $fieldName
     * @param  array  $metadata
     * @return boolean
     */
    private function canApplyFieldAcl($fieldName, $metadata)
    {
        // Model-level row uses the model name as "field"; always allow.
        if (!isset($metadata['fields'][$fieldName])) {
            return true;
        }

        $isAllowed = true;

        if (isset($metadata['fields'][$fieldName]['identifier'])
            || isset($metadata['fields'][$fieldName]['relatedIdentifier'])
            || isset($metadata['fields'][$fieldName]['linkedTo'])) {
            $isAllowed = false;
        }

        if ($isAllowed && isset($metadata['fields'][$fieldName]['acl'])) {
            $isAllowed = ($metadata['fields'][$fieldName]['acl']);
        }

        return $isAllowed;
    }
}
