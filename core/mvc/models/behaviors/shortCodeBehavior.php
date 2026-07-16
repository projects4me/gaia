<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior is used to generate unique short code before the creation of project.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class shortCodeBehavior extends Behavior implements BehaviorInterface
{
    /**
     * This function is called whenever an event is triggered
     * from a model e.g. beforeCreate or afterUpdate
     *
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            $this->$eventType($model);
        }
    }

    /**
     * This function is called before a model is created.
     *
     * @param  ModelInterface $model
     * @return void
     */
    protected function beforeValidationOnCreate(&$model)
    {
        $model->shortCode = $this->generateShortCode($model->name);
    }

    /**
     * This function generates a short code based on the project name.
     *
     * @param  string $name
     * @return string
     */
    public function generateShortCode($name)
    {
        if (strpos($name, ' ') !== false) {
            return $this->generateShortCodeFromMultipleWords($name);
        } else {
            return $this->generateShortCodeFromSingleWord($name);
        }
    }

    /**
     * Generate a short code from multiple words.
     *
     * @param  string $name
     * @return string
     */
    protected function generateShortCodeFromMultipleWords($name)
    {
        $words = explode(' ', $name);
        $shortCode = '';

        foreach ($words as $word) {
            $shortCode .= substr($word, 0, 1);
        }

        $shortCode = $this->extractShortCode($shortCode);
        $shortCode = $this->updateShortCodeIfExistsInDatabase($shortCode);

        return strtolower($shortCode);
    }

    /**
     * Generate a short code from a single word.
     *
     * @param  string $name
     * @return string
     */
    protected function generateShortCodeFromSingleWord($name)
    {
        $shortCode = strtolower(substr($name, 0, 5));
        $shortCode = $this->extractShortCode($shortCode);

        // Check if the short code exists in the database
        $shortCode = $this->updateShortCodeIfExistsInDatabase($shortCode);

        return strtolower($shortCode);
    }

    /**
     * Check if the short code exists in the database. If exists then append a unique number at the
     * last of the shortcode.
     *
     * @param  string $shortCode
     * @return string
     */
    protected function updateShortCodeIfExistsInDatabase($shortCode)
    {
        $shortCodes = [];
        $number = '';

        $projects = $this->getProjectsByShortCode($shortCode);

        foreach ($projects as $project) {
            $shortCodes[] = $project->shortCode;
        }

        $numbers = [];

        foreach ($shortCodes as $item) {
            if (preg_match('/\d+/', $item, $matches)) {
                $numbers[] = (int)$matches[0];
            } else {
                $numbers[] = 0;
            }
        }

        if (!empty($numbers)) {
            $number = max($numbers);
            $number++;
        }

        $updatedShortCode = "$shortCode$number";
        return $updatedShortCode;
    }

    /**
     * Retrieve projects by short code from database.
     *
     * @param  string $shortCode
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public function getProjectsByShortCode($shortCode)
    {
        $condition = "shortCode LIKE $shortCode%";
        return \Gaia\MVC\Models\Project::find(
            [
            "conditions" => "shortCode LIKE '$shortCode%'",
            ]
        );
    }

    /**
     * Extract the short code from the string. This function removes all the special characters
     * and numbers from the string.
     *
     * @param  string $shortCode
     * @return string
     */
    private function extractShortCode($shortCode)
    {
        $shortCode = preg_replace("/[^a-zA-Z]/", "", $shortCode);
        return $shortCode;
    }
}
