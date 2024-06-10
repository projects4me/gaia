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
            'sort' => 'child.lft',
            'order' => 'DESC'
        );

        $data = $this->readAll($params);
        return $data['baseModel'];
    }

    /**
     * This function is used to add a Resource model from the database by maintaining the hierarchical
     * structure (nested set model).
     *
     * @param  string $parentEntity
     * @param  array  $values
     * @return \Gaia\MVC\Models\Resource
     */
    public static function addResource($parentEntity, $values)
    {
        $groupClause = "AND groupName = '{$values['groupName']}'";

        //update resource tree before inserting new node
        $parentNode = Resource::findFirst("entity='$parentEntity' $groupClause");

        if ($parentNode) {
            $parentRHT = $parentNode->rht;
            $updateLFTPhql = "UPDATE resources set lft = lft+2 where lft>=$parentRHT $groupClause";
            $updateRHTPhql = "UPDATE resources set rht = rht+2 where rht>=$parentRHT $groupClause";

            \Phalcon\Di::getDefault()->get('db')->query($updateLFTPhql);
            \Phalcon\Di::getDefault()->get('db')->query($updateRHTPhql);

            $values['lft'] = $parentRHT;
            $values['rht'] = $parentRHT + 1;
            $values['parentId'] = $parentNode->id;
        }

        $resource = new Resource();
        $resource->assign($values);
        $resource->save($values);

        return $resource;
    }

    /**
     * This function is used to delete a Resource model from the database by maintaining the hierarchical
     * structure (nested set model).
     *
     * @param string $entityName
     */
    public static function deleteResource($entityName, $groupName)
    {
        $groupClause = "AND groupName='$groupName'";

        //update resource tree before deleting node
        $node = Resource::findFirst("entity='$entityName' $groupClause");

        $nodeRHT = $node->rht;
        $nodeLFT = $node->lft;

        $rangeWidth = ($nodeRHT - $nodeLFT) + 1;
        $updateLFTPhql = "UPDATE resources set lft = lft-$rangeWidth where lft>=$nodeRHT $groupClause";
        $updateRHTPhql = "UPDATE resources set rht = rht-$rangeWidth where rht>=$nodeRHT $groupClause";

        \Phalcon\Di::getDefault()->get('db')->query($updateLFTPhql);
        \Phalcon\Di::getDefault()->get('db')->query($updateRHTPhql);

        $node->delete();
    }

    /**
     * This function is used to add all resources into the database on which we can apply
     * acl permission. First we're adding an default parent 'App' resource and all of the
     * models e.g Issue, Project etc will be treated as child of this `App` resource. After
     * that the model name itself e.g. Project is added into the database as resource and
     * at the last the model's fields are added into the database.
     *
     * @param  string $groupName The name of the group for which the resource is being saved. In
     *                           our backend case this would be 'gaia'. Through this we can distinguish
     *                           between multiple platforms e.g. frontend, backend etc.
     * @return void
     */
    public static function addResourcesIntoDatabase($groupName)
    {
        $di = \Phalcon\Di::getDefault();
        $models = $di->get('config')->get('models')->toArray();

        // Add "App" resource as a parent for all of the models (resources).
        (new self())->addResourceIntoDatabase('App', $groupName, null, 1, 2);

        // Add "App" resource as a parent for for frontend group "prometheus"
        (new self())->addResourceIntoDatabase('App', 'prometheus', null, 1, 2);

        foreach ($models as $modelName) {
            $modelNamespace = "\\Gaia\\MVC\\Models\\{$modelName}";
            $model = new $modelNamespace();

            // If ACL is allowed on a model then we'll add model fields into the database.
            if ($model->isAclAllowed()) {
                $metadata = $di->get('metaManager')->getModelMeta($modelName);

                (new self())->addResourceIntoDatabase($modelName, $groupName, 'App');

                // Add model fields into db.
                foreach ($metadata['fields'] as $field) {
                    // No need to add
                    if (!$field['identifier']) {
                        $resourceName = "$modelName.{$field['name']}";
                        (new self())->addResourceIntoDatabase($resourceName, $groupName, $modelName);
                    }
                }
            }
        }
    }

    /**
     * This function is used to add resource into the database.
     *
     * @param  string $entity       The name of the resource.
     * @param  string $groupName    The name of the group for which the resource is being saved. In
     *                              our backend case this would be 'gaia'. Through this we can
     *                              distinguish between multiple platforms e.g. frontend, backend
     *                              etc.
     * @param  string $parentEntity The name of parent resource (if any).
     * @param  int    $lft          The left value for the resource node used to check how many childs does
     *                              that resource have.
     * @param  int    $rht          The right value for the resource node used to check how many childs does
     *                              that resource have.
     * @return void
     */
    protected static function addResourceIntoDatabase($entity, $groupName, $parentEntity = null, $lft = null, $rht = null)
    {
        // Check if the resource exists or not.
        $resource = \Gaia\MVC\Models\Resource::findFirst("entity='$entity' AND groupName ='$groupName'");

        if(!$resource) {
            // Add model as a resource into db.
            (new self())->addResource(
                $parentEntity,
                [
                'id' => create_guid(),
                'entity' => $entity,
                'groupName' => $groupName,
                'lft' => $lft,
                'rht' => $rht
                ]
            );
        }
    }
}
