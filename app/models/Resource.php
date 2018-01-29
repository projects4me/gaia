<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Foundation\MVC\Model;


/**
 * Resource Model
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Resource extends Model
{
    /**
     * Method getResources
     * @return List of Resources
     */
    public function getResource($entity){
      $params = array(
          'fields' => array('child.*'),
          'rels' => array('child'),
          'where' => '(Resource.entity : '.$entity.')',
          'sort' => 'child.lft',
          'order' => 'DESC'
      );

      return $this->readAll($params);
    }

}
