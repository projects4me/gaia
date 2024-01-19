<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\MVC\REST\Controllers\AclAdminController;

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
            $resourceName = $appliedPermission['attributes']['resourceName'];
            $permissionsMap[$resourceName] = $appliedPermission;
        }

        // Merge applied permission into default against the resource name.
        foreach ($defaultPermissions['data'] as $index => $defaultPermission) {
            $resourceName = $defaultPermission['attributes']['resourceName'];
            if (isset($permissionsMap[$resourceName]) === true) {
                unset($permissionsMap[$resourceName]['attributes']['resourceId']);
                $defaultPermissions['data'][$index] = $permissionsMap[$resourceName];
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

        if (is_dir($path) === true) {
            // Get all models names.
            $models = scandir($path);

            $permissionInterface = $this->getPermissionInterface($path);
            $permissionIndex = 0;
            foreach ($models as $modelFileName) {
                // Get metadata of the model.
                if ($modelFileName !== '.' && $modelFileName !== '..') {
                    $metaFilePath = $path . '/' . $modelFileName;
                    $modelName = str_replace('.php', '', $modelFileName);
                    $this->addPermissions($permissionIndex, $modelName, [$modelName], $permissions, $permissionInterface, false);

                    $data = $this->di->get('fileHandler')->readFile($metaFilePath);

                    $fieldTypes = ['fields', 'relationships'];
                    foreach ($fieldTypes as $fieldType) {
                        // Here fields can be relationship types when $fieldType is relationships.
                        $fields = array_keys($data[$modelName][$fieldType]);

                        if ($fieldType !== 'relationships') {
                            $this->addPermissions($permissionIndex, $modelName, $fields, $permissions, $permissionInterface, true);
                        } else {
                            $relatedTypes = $fields;
                            foreach ($relatedTypes as $relType) {
                                $rels = array_keys($data[$modelName][$fieldType][$relType]);
                                $this->addPermissions($permissionIndex, $modelName, $rels, $permissions, $permissionInterface, true);
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
     * @return void
     */
    protected function addPermissions(&$permissionIndex, $modelName, $fields, &$permissions, $permissionInterface, $addPrefix)
    {
        $modelFields = $fields;
        if ($addPrefix === true) {
            $modelFields = array_map(
                function ($field) use ($modelName) {
                    return "{$modelName}.{$field}";
                }, $fields
            );
        }

        // Add fields.
        foreach ($modelFields as $modelField) {
            $permissionIndex++;
            $permissionInterface['attributes']['resourceName'] = $modelField;
            $permissionInterface['id'] = "new_{$permissionIndex}";
            $permissions['data'][] = $permissionInterface;
        }
    }

    /**
     * This method returns permission interface.
     *
     * @method getPermissionInterface
     * @return array
     */
    protected function getPermissionInterface($path)
    {
        $permissionInterface = ['type' => 'Permission'];
        $permissionInterface['attributes'] = [];
        $permissionInterface['id'] = '';
        $permissionModel = $this->di->get('fileHandler')->readFile("{$path}/Permission.php");

        $permissionFields = array_keys($permissionModel['Permission']['fields']);
        $notRequiredFields = ['resourceId', 'controllerId', 'id'];

        $fields = array_diff($permissionFields, $notRequiredFields);
        foreach ($fields as $field) {
            $permissionInterface['attributes'][$field] = '';
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

            $fields = ["Permission.*"];
            $fields[] = $maps['fields'][$queryParam];

            $params = [
                'where' => $query,
                'rels' => $maps['rels'][$queryParam],
                'fields' => $fields,
            ];

            $permissionModel = new $this->modelName();
            $data = $permissionModel->readAll($params);
            $dataArray = $this->extractData($data);
            $appliedPermissions['data'] = array_merge($appliedPermissions['data'], $dataArray['data']);
        }

        return $appliedPermissions;
    }
}
