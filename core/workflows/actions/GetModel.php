<?php

namespace Gaia\Workflows\Actions;

/**
 * Handles retrieval of model data based on specified model name and conditions.
 *
 * @package Gaia\Workflows\Actions
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class GetModel
{
    /**
     * Executes the model retrieval operation
     *
     * @param string $modelName The name of the model class to instantiate
     * @param string $where The WHERE clause conditions for filtering data
     * @return array Returns the retrieved model data as an array
     * @throws \Gaia\Exception\Exception When the specified model class is not found
     */
    public static function execute(string $modelName, string $where): object
    {
        $modelName = ucfirst($modelName);
        $modelNamespace = "\\Gaia\\MVC\\Models\\$modelName";
        $relationship = new \Gaia\Core\MVC\Models\Relationship(\Phalcon\Di::getDefault());
        $params = [
            'where' => $where
        ];

        if (class_exists($modelNamespace)) {
            $model = new $modelNamespace();
        } else {
            throw new \Gaia\Exception\Exception("Class {$modelName} not found");
        }

        $data = $model->readAll($params);
        $result = isset($data['baseModel']) ? $data['baseModel'] : [];
        return $result;
    }
}
