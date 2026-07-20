<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Resource Model
 *
 * @author   Rana Nouman <ranamnouman@gmail.com>
 * @package  Foundation
 * @category Model
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Resource extends Model
{
    /**
     * Method getResources
     *
     * @param  string $entity
     * @return List of Resources
     */
    public function getResource($entity)
    {
        $params = array(
            'fields' => array('child.*'),
            'rels' => array('child'),
            'where' => '(Resource.entity : ' . $entity . ')',
            'sort' => 'child.entity',
            'order' => 'DESC'
        );

        $data = $this->readAll($params);
        return $data['baseModel'];
    }

    /**
     * Add a resource. When $parentEntity is null/empty, inserts a root row.
     * Otherwise links the new row to the parent via parentId.
     *
     * @param  string|null $parentEntity
     * @param  array       $values
     * @return \Gaia\MVC\Models\Resource
     */
    public static function addResource($parentEntity, $values)
    {
        $groupClause = "AND groupName = '{$values['groupName']}'";

        if (!empty($parentEntity)) {
            $parentNode = Resource::findFirst("entity='$parentEntity' $groupClause");

            if ($parentNode) {
                $values['parentId'] = $parentNode->id;
            }
        } else {
            $values['parentId'] = null;
        }

        $resource = new Resource();
        $resource->assign($values);
        $resource->save($values);

        return $resource;
    }

    /**
     * Delete a Resource by entity name within a group.
     *
     * @param string $entityName
     * @param string $groupName
     */
    public static function deleteResource($entityName, $groupName)
    {
        $groupClause = "AND groupName='$groupName'";
        $node = Resource::findFirst("entity='$entityName' $groupClause");

        if ($node) {
            $node->delete();
        }
    }

    /**
     * Seed model resources and their field children for RBAC.
     * Models are flat roots (no App parent). Fields are nested under their model via parentId.
     *
     * @param  string $groupName Resource group (gaia).
     * @return void
     */
    public static function addResourcesIntoDatabase($groupName)
    {
        global $currentUser;
        if (!isset($currentUser->id)) {
            $currentUser->id = 'system';
            $currentUser->name = 'system_user';
        }
        $di = \Phalcon\Di::getDefault();
        $models = $di->get('config')->get('models')->toArray();

        foreach ($models as $modelName) {
            $modelNamespace = "\\Gaia\\MVC\\Models\\{$modelName}";
            $model = new $modelNamespace();

            if ($model->isAclAllowed()) {
                $metadata = $di->get('metaManager')->getModelMeta($modelName);

                // Flat model root (no App parent).
                self::addResourceIntoDatabase($modelName, $groupName, null);

                // Field children under the model.
                foreach ($metadata['fields'] as $field) {
                    if (empty($field['identifier'])) {
                        $resourceName = "{$modelName}.{$field['name']}";
                        self::addResourceIntoDatabase($resourceName, $groupName, $modelName);
                    }
                }
            }
        }
    }

    /**
     * Insert a resource if it does not already exist.
     * Model roots are flat. Field resources are children via parentId.
     *
     * @param  string      $entity
     * @param  string      $groupName
     * @param  string|null $parentEntity Parent model entity for field resources; null for models.
     * @return void
     */
    protected static function addResourceIntoDatabase($entity, $groupName, $parentEntity = null)
    {
        $resource = \Gaia\MVC\Models\Resource::findFirst("entity='$entity' AND groupName ='$groupName'");

        if (isset($resource) && !empty($resource)) {
            return;
        }

        if (!empty($parentEntity)) {
            $parent = \Gaia\MVC\Models\Resource::findFirst(
                "entity='{$parentEntity}' AND groupName ='{$groupName}'"
            );
            if (!$parent) {
                return;
            }

            $child = new Resource();
            $child->assign([
                'id' => create_guid(),
                'entity' => $entity,
                'groupName' => $groupName,
                'parentId' => $parent->id,
            ]);
            $child->save();
            return;
        }

        self::addResource(
            null,
            [
                'id' => create_guid(),
                'entity' => $entity,
                'groupName' => $groupName,
            ]
        );
    }
}
