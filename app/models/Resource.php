<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;

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
   * Flag decides whether to execute hasManyToMany relationship queries
   * separately or not.
   * 
   * @var bool
   */
  public $splitQueries = true;

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

}
