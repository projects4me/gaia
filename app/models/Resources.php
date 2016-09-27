<?php

use Foundation\Mvc\Model;

/**
 *
 */
class Resources extends Model
{
    /**
     * Method getResources
     * @return List of Resources
     */
    public function getResource($entity){
      $params = array(
          'fields' => array('ChildResources.*'),
          'rels' => array('ChildResources'),
          'where' => '(Resources.entity : '.$entity.')',
          'sort' => 'ChildResources.lft',
          'order' => 'DESC'
      );

      return $this->readAll($params);
    }

}
