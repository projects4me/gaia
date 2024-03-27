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
        $allowedRelTypes = ['hasOne', 'belongsTo'];

        // Get all models names.
        global $settings;
        $models = $settings['models'];

        $permissionInterface = $this->getPermissionInterface($path);
        $permissionIndex = 0;
        foreach ($models as $modelName) {
            if ($this->canApplyModelAcl($modelName)) {
                // Get metadata of the model.
                $data = $this->di->get('metaManager')->getModelMeta($modelName);

                $this->addPermissions($permissionIndex, $modelName, [$modelName], $permissions, $permissionInterface, false, $data);

                $requestedFieldTypes = ['fields', 'relationships'];
                foreach ($requestedFieldTypes as $requestedFieldType) {
                    // Here fields can be relationship types when $requestedFieldType is relationships.
                    $requestedFields = array_keys($data[$requestedFieldType]);

                    if ($requestedFieldType !== 'relationships') {
                        $this->addPermissions($permissionIndex, $modelName, $requestedFields, $permissions, $permissionInterface, true, $data);
                    } else {
                        $relatedTypes = $requestedFields;
                        foreach ($relatedTypes as $relType) {

                            // Only create permissions for relationships of type hasOne and belongsTo.
                            if (in_array($relType, $allowedRelTypes)) {
                                $rels = array_keys($data[$requestedFieldType][$relType]);
                                $this->addPermissions($permissionIndex, $modelName, $rels, $permissions, $permissionInterface, true, $data);
                            }
                        }
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
     * @param  array  $requestedFields     Fields array.
     * @param  array  $permissions         Permissions array.
     * @param  array  $permissionInterface Permission default interface.
     * @param  bool   $addPrefix           Boolean flag to add model as a prefix on field.
     * @param  array  $metadata            The metadata of given model.
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

        // Add fields.
        foreach ($modelFields as $modelField) {
            list(, $fieldName) = explode('.', $modelField);

            $fieldName = $fieldName ?? $modelField;

            /*
            * If the field is 'id' or any other custom identifier e.g. issueNumber for issue than that
            * field will not become the part of permissions array.
            */
            if ($this->canApplyFieldAcl($fieldName, $metadata)) {
                $permissionIndex++;
                $permissionInterface['attributes']['resourceName'] = $modelField;
                $permissionInterface['attributes']['resourceId'] = "new_resource_{$permissionIndex}";
                $permissionInterface['id'] = create_guid();
                $permissions['data'][] = $permissionInterface;
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

            // Retrieve Resource from database using given resource name.
            $metadataPath = APP_PATH . '/app/metadata/model';
            $resourceModel = \Gaia\MVC\Models\Resource::findFirst("entity='{$resourceName}'");

            // Get metadata of the resource.
            $requestedModelName = $resourceModel->entity;
            $resourceMetaData = $this->getDI()->get('metaManager')->getModelMeta($requestedModelName);

            if (str_contains($requestedModelName, '.') === true) {
                list($requestedModule, $requestedField) = explode(".", $requestedModelName);
                $requestedModelName = $requestedModule;
            }

            if ($requestedField) {
                $this->passFieldChecks(
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
    }

    /**
     * This function verify all checks, related to fields, that are required to create a permission.
     *
     * @method passFieldChecks
     * @param  array $permissionFlags Array of permission flags.
     * @param  array $values          Array containing values of the request.
     * @return bool
     */
    private function passFieldChecks($permissionFlags, $values)
    {
        $allowedAccessLevels = ["0", "9"];

        foreach ($permissionFlags as $flag) {
            if (!empty($values[$flag]) && !in_array($values[$flag], $allowedAccessLevels)) {
                $errorMessage = "You're not allowed to set {$values[$flag]}";
                $suggestion = "You can only set 0 and 9 access levels";
                throw new \Gaia\Exception\Permission(
                    $errorMessage,
                    null,
                    null,
                    [
                        "suggestion" => $suggestion
                    ]
                );
            }
        }

        return true;
    }

    /**
     * This function verify all checks, related to model, that are required to create a permission.
     *
     * @method passModelChecks
     * @param  array  $resourceMeta       The array containing metadata of resource.
     * @param  string $requestedModelName The name of the model for which permission is going to be created.
     * @param  array  $permissionFlags    Array of permission flags.
     * @param  array  $values             Array containing values of the request.
     * @return bool
     */
    private function passModelChecks($resourceMeta, $requestedModelName, $permissionFlags, $values)
    {
        $allowedGroups = $this->getDI()->get('metaManager')->getGroups();
        $modelGroups = $this->getDI()->get('metaManager')->getModelGroups($requestedModelName);

        $allowedPermissions = ['10', '11', '12', '90', '91', '99'];

        // Check whether the given resource/model is dependent on group or not.
        if ($modelGroups) {
            $this->passGroupDependentCheck($modelGroups, $permissionFlags, $allowedPermissions, $values, $requestedModelName);
        } elseif (in_array($requestedModelName, $allowedGroups)) {
            $this->passSelfDependentCheck($permissionFlags, $allowedPermissions, $values, $requestedModelName);
        }

        return true;
    }

    /**
     * This function is called when a resource is dependent on some groups and is used to verify all checks required
     * to create permission.
     *
     * @method passGroupDependentCheck
     * @param  array  $modelGroups        Array containing the name of groups on which the resource in dependent.
     * @param  array  $permissionFlags    Array of permission flags.
     * @param  array  $allowedPermissions The array of allowed permissions.
     * @param  array  $values             Array containing values of the request.
     * @param  string $requestedModelName The name of the model for which permission is going to be created.
     * @return bool
     */
    private function passGroupDependentCheck(
        $modelGroups,
        $permissionFlags,
        $allowedPermissions,
        $values,
        $requestedModelName
    ) {
        foreach ($modelGroups as $group) {
            $groupPermission = $this->retrievePermission(ucfirst($group), $values);

            if ($groupPermission) {
                foreach ($permissionFlags as $flag) {
                    $permissionSet = "{$groupPermission->$flag}{$values[$flag]}";
                    if (!in_array($permissionSet, $allowedPermissions)) {
                        $errorMessage = "You cannot set access level of {$values[$flag]} on {$requestedModelName} module because its group '{$group}' is having access level of {$groupPermission->$flag}.";
                        $suggestion = "You can use access levels e.g. 0, 1, 2 and 9";
                        throw new \Gaia\Exception\Permission(
                            $errorMessage,
                            null,
                            null,
                            [
                                "suggestion" => $suggestion
                            ]
                        );
                    }
                }
            }
        }

        return true;
    }

    /**
     * This function is called when a resource is itself a group and verify all checks required to create permission.
     *
     * @method passSelfDependentCheck
     * @param  array  $permissionFlags    Array of permission flags.
     * @param  array  $allowedPermissions The array of allowed permissions.
     * @param  array  $values             Array containing values of the request.
     * @param  string $requestedModelName The name of the model for which permission is going to be created.
     * @return bool
     */
    private function passSelfDependentCheck($permissionFlags, $allowedPermissions, $values, $requestedModelName)
    {
        /*
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
            $group = $this->getDI()->get('metaManager')->getModelGroups($modelName);

            if (in_array($requestedModelName, $group)) {
                $dependentModels[] = $modelName;
            }
        }

        // Retrieve permissions of dependent models.
        foreach ($dependentModels as $dependentModel) {
            $permission = $this->retrievePermission($dependentModel, $values);
            ($permission) && ($dependentPermissions[$dependentModel] = $permission);
        }

        // Iterate all of the dependent permissions and check the eligibility.
        foreach ($dependentPermissions as $dependentModel => $dependentPermission) {
            foreach ($permissionFlags as $flag) {
                $permissionSet = "{$values[$flag]}{$dependentPermission->$flag}";
                if (!in_array($permissionSet, $allowedPermissions)) {
                    $errorMessage = "You cannot set access level of {$values[$flag]} on {$requestedModelName} module because its dependent group '{$dependentModel}' is having access level of {$dependentPermission->$flag}.";
                    $suggestion = "You can use access levels e.g. 1 and 9";
                    throw new \Gaia\Exception\Permission(
                        $errorMessage,
                        null,
                        null,
                        [
                            "suggestion" => $suggestion
                        ]
                    );
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
     * @param  string $resourceName The name of resource.
     * @param  array  $values       The array of default values for permission model.
     * @return \Gaia\MVC\Models\Permission
     */
    protected function retrievePermission($resourceName, $values)
    {
        $resource = \Gaia\MVC\Models\Resource::findFirst("entity='{$resourceName}'");
        // Check that permission is available against given resourceId, roleId or controllerId.
        $expectedRelatedIds = ['roleId', 'controllerId'];

        foreach ($expectedRelatedIds as $expectedRelatedId) {
            $clause = "resourceId='{$resource->id}'";
            if (isset($values[$expectedRelatedId])) {
                $clause .= " AND {$expectedRelatedId}='{$values[$expectedRelatedId]}'";
                $permission = \Gaia\MVC\Models\Permission::findFirst($clause);
            }
        }
        return $permission;
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

        if (str_contains($resourceName, ".")) {
            list($modelName, $fieldName) = explode(".", $resourceName);
        }

        $metadata = $this->di->get('metaManager')->getModelMeta($modelName);

        $action = $actionsMap[$this->actionName];

        if ($fieldName && !$this->canApplyFieldAcl($fieldName, $metadata)) {
            throw new \Gaia\Exception\Exception("Permission cannot ". $action);
        }

        if (!$this->canApplyModelAcl($modelName)) {
            throw new \Gaia\Exception\Exception("Permission cannot ". $action);
        }

        return true;
    }

    /**
     * Checks that whether we can apply acl on the model or not. If not allowed then we can't able to
     * create the permission against the given resource.
     *
     * @method canApplyModelAcl
     * @param  string $modelName Name of the resource on which acl is being checked.
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
     * Checks that whether we can apply acl on the field/relationship or not. If not allowed then we can't able to
     * create the permission against the given field/relationship.
     *
     * @method canApplyFieldAcl
     * @param  string $fieldName Name of the field/relationship on which acl is being checked.
     * @param  array  $metadata  The metadata of given model.
     * @return boolean
     */
    private function canApplyFieldAcl($fieldName, $metadata)
    {
        $isAllowed = true;

        /*
        * Check whether the given resource is field or not. If it's field then check whether this is used for links
        * generation or not. If used then acl is not allowed on this field.
        */
        if ($fieldName && isset($metadata['fields'][$fieldName]['link']) || $fieldName === 'id') {
            $isAllowed = false;
        }

        /*
        * Check whether the given resource is relationship or not. If it's relationship then check whether
        * this is used for links generation or not. If used then acl is not allowed on this relationship.
        */
        if ($fieldName && isset($metadata['relationships'][$fieldName]['link'])) {
            $isAllowed = false;
        }

        return $isAllowed;
    }
}
