<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior checks an agent's runtime config: `modelConfig` is a JSON
 * object (an object sent as JSON is stored as its JSON text), `maxTurns` and
 * `maxRunsPerIssue` are positive whole numbers, and `userId` never changes
 * once the agent exists, so the agent stays tied to its users row.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class agentConfigBehavior extends Behavior implements BehaviorInterface
{
    /**
     * Fields that must hold a positive whole number when set.
     *
     * @var array
     */
    public const POSITIVE_INT_FIELDS = ['maxTurns', 'maxRunsPerIssue'];

    /**
     * Returning false from a before* event cancels the save.
     *
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            return $this->$eventType($model);
        }
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnCreate($model)
    {
        return $this->checkConfig($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        if (is_array($snapshot) && isset($snapshot['userId'])
            && (string) $snapshot['userId'] !== (string) $model->userId
        ) {
            $model->appendMessage(new Message('The user of an agent cannot be changed', 'userId'));
            return false;
        }

        return $this->checkConfig($model);
    }

    /**
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkConfig($model)
    {
        $modelConfig = $model->readAttribute('modelConfig');
        if (is_array($modelConfig)) {
            $modelConfig = json_encode($modelConfig);
            $model->writeAttribute('modelConfig', $modelConfig);
        }

        if ($modelConfig !== null && $modelConfig !== '') {
            $decoded = json_decode((string) $modelConfig, true);
            if (!is_array($decoded)) {
                $model->appendMessage(new Message('modelConfig must be a JSON object', 'modelConfig'));
                return false;
            }
        }

        foreach (self::POSITIVE_INT_FIELDS as $field) {
            $value = $model->readAttribute($field);
            if ($value === null || $value === '') {
                continue;
            }
            if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $model->appendMessage(new Message($field . ' must be a positive whole number', $field));
                return false;
            }
        }

        return true;
    }
}
