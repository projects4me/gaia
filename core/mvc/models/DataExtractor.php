<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Core\MVC\Models;

use Phalcon\Mvc\Model\Resultset;

/**
 * This class is used extract data set having different types.
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
                /**
                 * If no fields are requested then we'll get two models (for hasManyToMany).
                 * We only have to deal with that model which contains relatedId.
                 */
                if ($values[$relName]) {
                    $relatedModel = $values[$relName];

                    if ($relatedModel[$key]) {
                        $ids[] = $relatedModel[$key];
                    }
                    elseif ($relatedModel['relatedId']) {
                        $ids[] = $relatedModel['relatedId'];
                    }
                }
                else {
                    //if some fields are requested then we'll get data as scalar values.
                    foreach ($values as $attr => $value) {
                        if ($attr == $key) {
                            $ids[] = $value;
                        }
                        else if ($attr == 'relatedId') {
                            $ids[] = $value;
                        }
                    }
                }
            }
        }
        return $ids;
    }

}