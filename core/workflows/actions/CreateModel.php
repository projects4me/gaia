<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Workflows\Actions;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Provides functionality to dynamically create and save model instances
 *
 * This class contains a static method to create model instances based on model name,
 * assign data to them and save them to the database.
 *
 * @package Gaia\Workflows\Actions
 * @author  Rana Nouman <ranamnouman@gmail.com>
 * @license http://legal.projects4.me/LICENSE.txt AGPLv3
 */
class CreateModel
{
    /**
     * Creates and saves a new model instance
     *
     * @param string $modelName The name of the model class to create
     * @param array $data Optional array of data to assign to the model
     * @return mixed The created and saved model instance
     * @throws \Gaia\Exception\Exception If the model class doesn't exist or save fails
     */
    public static function execute(string $modelName, array $data = [])
    {
        $className = "\\Gaia\\MVC\\Models\\" . ucfirst($modelName);
        if (class_exists($className)) {
            $model = new $className();
        } else {
            throw new \Gaia\Exception\Exception("Class {$modelName} not found");
        }

        if (!isset($data['id'])) {
            $model->id = create_guid();
        }

        $model->assign($data);

        if (!$model->save()) {
            throw new \Gaia\Exception\Exception("Failed to create {$modelName}");
        }

        return $model;
    }
}
