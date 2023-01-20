<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models;

use Phalcon\Mvc\Model\Resultset;

/**
 * This class is used extract data set having different types.
 * 
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Core\Mvc\Models
 * @category DataExtractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DataExtractor
{

    /**
     * This function is used to extract base model ids.
     * 
     * @param array $models Array of model.
     * @return array
     */
    public static function extractModelIds($models)
    {
        $ids = array();
        foreach ($models as $model) {
            $ids[] = $model['id'];
        }
        return $ids;
    }

    /**
     * This function is used to extract related model ids.
     * 
     * @param string $relName Name of relationship.
     * @param array $data Arry of related model.
     * @param string $key
     * @return array
     */
    public static function extractRelIds($relName, $data, $key)
    {
        $ids = array();
        if ($data instanceof Resultset) {
            $data->setHydrateMode(Resultset::HYDRATE_ARRAYS);
            foreach ($data as $values) {
                foreach ($values as $attr => $value) {
                    if ($attr == $relName) {

                        //extract "modelId" from result of related
                        if ($value[$key]) {
                            $ids[] = $value[$key];
                        }
                        else if ($value['relatedId']) {
                            $ids[] = $value['relatedId'];
                        }
                    }
                }
            }
        }
        return $ids;
    }
}