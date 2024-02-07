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
     * This components that this controller uses
     *
     * @var $uses
     * @type array
     */
    public $uses = array('Permission');

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
            $requestedResourceName = $appliedPermission['attributes']['resourceName'];
            $permissionsMap[$requestedResourceName] = $appliedPermission;
        }

        // Merge applied permission into default against the resource name.
        foreach ($defaultPermissions['data'] as $index => $defaultPermission) {
            $requestedResourceName = $defaultPermission['attributes']['resourceName'];
            if (isset($permissionsMap[$requestedResourceName]) === true) {
                $defaultPermissions['data'][$index] = $permissionsMap[$requestedResourceName];
            }
        }

        $this->finalData = $this->buildHAL($defaultPermissions);

        return $this->returnResponse($this->finalData);
    }

    /**
     * This method is used to prepare and return the default permissions by traversing all of the models
     * metadata.
     *
     * @method getDefaultPermissions
     * @return array
     */
    protected function getDefaultPermissions()
    {
        $path = APP_PATH . '/app/metadata/model';
        $permissions = [];

        // Get all models names.
        global $settings;
        $models = $settings['models'];

        $permissionInterface = $this->getPermissionInterface($path);
        $permissionIndex = 0;
        foreach ($models as $modelName) {
            // Get metadata of the model.
            $metaFilePath = $path . '/' . $modelName . '.php';
            $this->addPermissions($permissionIndex, $modelName, [$modelName], $permissions, $permissionInterface, false);

            $data = $this->di->get('fileHandler')->readFile($metaFilePath);

            $requestedFieldTypes = ['fields', 'relationships'];
            foreach ($requestedFieldTypes as $requestedFieldType) {
                // Here fields can be relationship types when $requestedFieldType is relationships.
                $requestedFields = array_keys($data[$modelName][$requestedFieldType]);

                if ($requestedFieldType !== 'relationships') {
                    $this->addPermissions($permissionIndex, $modelName, $requestedFields, $permissions, $permissionInterface, true);
                } else {
                    $relatedTypes = $requestedFields;
                    foreach ($relatedTypes as $relType) {
                        $rels = array_keys($data[$modelName][$requestedFieldType][$relType]);
                        $this->addPermissions($permissionIndex, $modelName, $rels, $permissions, $permissionInterface, true);
                    }
                }
            }
        }

        return $permissions;
    }

    /**
     * This method is used to create permission model by the given field lists and return the permissions array.
     *
     * @method addPermissions
     * @param  int    $permissionIndex     Permissions array index.
     * @param  string $modelName           Model name.
     * @param  array  $requestedFields              Fields array.
     * @param  array  $permissions         Permissions array.
     * @param  array  $permissionInterface Permission default interface.
     * @param  bool   $addPrefix           Boolean flag to add model as a prefix on field.
     * @return void
     */
    protected function addPermissions(&$permissionIndex, $modelName, $requestedFields, &$permissions, $permissionInterface, $addPrefix)
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

        // Add fields.
        foreach ($modelFields as $modelField) {
            $permissionIndex++;
            $permissionInterface['attributes']['resourceName'] = $modelField;
            $permissionInterface['attributes']['resourceId'] = "new_resource_{$permissionIndex}";
            $permissionInterface['id'] = "new_{$permissionIndex}";
            $permissions['data'][] = $permissionInterface;
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
            $dataArray = $this->extractData($data);
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
        $logger->debug('Gaia.core.controllers.permission->postAction');

        $util = new Util();
        $data = array();

        $requestData = $util->objectToArray($this->request->getJsonRawBody());

        /**
         * Fetch resource if not attached to permission and create permission. Below conditional statement
         * will work in case when post request is generated from frontend when a default permission, which is not
         * inside db, is updated.
         */
        if (str_contains($requestData['resourceId'], 'new')) {
            // All of the resources should be available inside the database.
            $requestData = $requestData['data']['attributes'];
        }

        if ($this->passPreReqs($requestData)) {
            return parent::postAction();
        }
    }

    /**
     * This function verify all of the checks that are required to create a permission.
     *
     * @method passPreReqs
     * @return bool
     */
    private function passPreReqs($values)
    {
        // Resource name required.
        $resourceName = $values['resourceName'];
        if (!$resourceName) {
            throw new \Gaia\Exception\Exception("Please specify resource name");
        }

        // Get permission flags from configurations.
        global $settings;
        $permissionFlags = $settings['system']['acl']['permissionFlags'];

        // Retrieve Resource from database using given resource name.
        $metadataPath = APP_PATH . '/app/metadata/model';
        $resourceModel = \Gaia\MVC\Models\Resource::findFirst("entity='{$resourceName}'");

        // Get metadata of the resource.
        $requestedModelName = $resourceModel->entity;
        $resourceMetaData = $this->di->get('fileHandler')->readFile("{$metadataPath}/{$requestedModelName}.php");

        if (str_contains($requestedModelName, '.')) {
            list($requestedModule, $requestedField) = explode(".", $requestedModelName);
            $requestedModelName = $requestedModule;
        }

        if ($requestedField) {
            $this->passFieldChecks(
                $requestedField,
                $requestedModelName,
                $permissionFlags,
                $values
            );
        } elseif (!$requestedField) {
            $this->passModelChecks(
                $resourceMetaData,
                $requestedModelName,
                $permissionFlags,
                $values
            );
        }

        return true;
    }

    /**
     * This function verify all checks, related to fields, that are required to create a permission.
     *
     * @method passFieldChecks
     * @return bool
     */
    private function passFieldChecks($requestedField, $requestedModelName, $permissionFlags, $values)
    {
        /**
         * 1st Check
         * If the isGroupDependent flag is true, which tells us that there is a group level access
         * e.g. 2, then check whether the requested field (resource) against which the permission is
         * trying to created is the field from the resource e.g. Issue then we can't process this request
         * as the fields cannot be group dependent.
         */
        $allowedAccessLevels = ["0", "9"];

        foreach ($permissionFlags as $flag) {
            if (!in_array($values[$flag], $allowedAccessLevels)) {
                throw new \Gaia\Exception\Exception("You can only set 0 and 9 access levels");
            }
        }

        return true;
    }

    /**
     * This function verify all checks, related to model, that are required to create a permission.
     *
     * @method passModelChecks
     * @return bool
     */
    private function passModelChecks($resourceMeta, $requestedModelName, $permissionFlags, $values)
    {
        $allowedGroups = ['project'];
        $allowedPermissions = ['10', '11', '12', '90', '91', '99'];
        $modelGroups = array_intersect($resourceMeta[$requestedModelName]['groups'], $allowedGroups);

        // Check whether the given resource/model is dependent on group or not.
        if ($modelGroups) {
            foreach ($modelGroups as $group) {
                $groupPermission = $this->retrievePermission(ucfirst($group), $values);

                if ($groupPermission) {
                    foreach ($permissionFlags as $flag) {
                        $permissionSet = "{$groupPermission->$flag}{$values[$flag]}";
                        if (!in_array($permissionSet, $allowedPermissions)) {
                            throw new \Gaia\Exception\Exception(
                                "You cannot set access level of {$values[$flag]} on {$requestedModelName} module because its group {$group} is having access level of {$groupPermission->$flag}."
                            );
                        }
                    }
                }
            }
        } elseif (in_array(strtolower($requestedModelName), $allowedGroups)) {
            /**
             * If the requested model is itself a group then fetch all of the list of dependent models and
             * retrieve there permissions and check the eligibility for permission creation.
             */

            global $settings;
            $path = APP_PATH . '/app/metadata/model';
            $models = $settings['models'];
            $dependentModels = [];
            $dependentPermissions = [];

            // Get list of dependent groups.
            foreach ($models as $modelName) {
                $metaFilePath = $path . '/' . $modelName . '.php';
                $data = $this->di->get('fileHandler')->readFile($metaFilePath);

                if (in_array(strtolower($requestedModelName), $data[$modelName]['groups'])) {
                    $dependentModels[] = $modelName;
                }
            }

            // Retrieve permissions of dependent models.
            foreach ($dependentModels as $dependentModel) {
                $permission = $this->retrievePermission($dependentModel, $values);
                ($permission) && ($dependentPermissions[$dependentModel] = $permission);
            }

            // Iterate all of the dependent permissions and check the eligibility.
            foreach ($dependentPermissions as $dependentPermission) {
                foreach ($permissionFlags as $flag) {
                    $permissionSet = "{$values[$flag]}{$dependentPermission->$flag}";
                    if (!in_array($permissionSet, $allowedPermissions)) {
                        throw new \Gaia\Exception\Exception(
                            "You cannot set access level of {$values[$flag]} on {$requestedModelName} module because its dependent group {$group} is having access level of {$dependentPermission->$flag}."
                        );
                    }
                }
            }
        }

        return true;
    }

    /**
     * This function is used to retrieve permission from the database against the given resourceName, roleId
     * or controllerId.
     *
     * @method retrievePermission
     * @return \Gaia\MVC\Models\Permission
     */
    protected function retrievePermission($resourceName, $values)
    {
        $resource = \Gaia\MVC\Models\Resource::findFirst("entity='{$resourceName}'");
        // Check that permission is available against given resourceId, roleId or controllerId.
        $expectedRelatedIds = ['roleId', 'controllerId'];

        foreach ($expectedRelatedIds as $expectedRelatedId) {
            $clause = "resourceId='{$resource->id}'";
            if ($values[$expectedRelatedId]) {
                $clause .= " AND {$expectedRelatedId}='{$values[$expectedRelatedId]}'";
                $permission = \Gaia\MVC\Models\Permission::findFirst($clause);
            }
        }
        return $permission;
    }
}
