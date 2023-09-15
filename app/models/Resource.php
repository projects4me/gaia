<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

/**
 * Resource Model
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Resource extends Model
{
    /**
     * Method getResources
     *
     * @param string $entity
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
     * @param string $parentEntity
     * @param array $values
     * @return \Gaia\MVC\Models\Resource
     */
    public static function addResource($parentEntity, $values)
    {
        //update resource tree before inserting new node
        $parentNode = Resource::findFirst("entity='$parentEntity'");
        $parentRHT = $parentNode->rht;

        $updateLFTPhql = "UPDATE resources set lft = lft+2 where lft>=$parentRHT";
        $updateRHTPhql = "UPDATE resources set rht = rht+2 where rht>=$parentRHT";

        \Phalcon\Di::getDefault()->get('db')->query($updateLFTPhql);
        \Phalcon\Di::getDefault()->get('db')->query($updateRHTPhql);

        //insert new node
        $values['lft'] = $parentRHT;
        $values['rht'] = $parentRHT + 1;

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
    public static function deleteResource($entityName)
    {
        //update resource tree before deleting node
        $node = Resource::findFirst("entity='$entityName'");

        $nodeRHT = $node->rht;
        $nodeLFT = $node->lft;

        $rangeWidth = ($nodeRHT - $nodeLFT) + 1;
        $updateLFTPhql = "UPDATE resources set lft = lft-$rangeWidth where lft>=$nodeRHT";
        $updateRHTPhql = "UPDATE resources set rht = rht-$rangeWidth where rht>=$nodeRHT";

        \Phalcon\Di::getDefault()->get('db')->query($updateLFTPhql);
        \Phalcon\Di::getDefault()->get('db')->query($updateRHTPhql);

        $node->delete();
    }
}
