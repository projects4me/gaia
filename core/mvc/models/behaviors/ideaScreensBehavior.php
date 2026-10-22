<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\MVC\Models\Upload;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior checks the screens of an idea message: a JSON array of
 * {uploadId, key, title, version, width, height}, where every uploadId is an
 * upload related to the message's idea. A screens array sent as JSON is stored
 * as its JSON text.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ideaScreensBehavior extends Behavior implements BehaviorInterface
{
    /**
     * The relatedTo value of uploads that belong to an idea.
     */
    public const RELATED_TO = 'idea';

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
        return $this->checkScreens($model);
    }

    /**
     * Only changed screens are checked again, so a later save (e.g. a soft
     * delete) does not fail because a referenced upload was removed since.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        $this->encodeScreens($model);
        if (is_array($snapshot) && array_key_exists('screens', $snapshot)
            && (string) $snapshot['screens'] === (string) $model->screens
            && (string) ($snapshot['ideaId'] ?? '') === (string) $model->ideaId
        ) {
            return true;
        }

        return $this->checkScreens($model);
    }

    /**
     * Store a screens array as its JSON text.
     *
     * @param  ModelInterface $model
     * @return void
     */
    protected function encodeScreens($model)
    {
        if (is_array($model->screens)) {
            $model->screens = json_encode($model->screens);
        }
    }

    /**
     * Reject screens that are not a list of screens of uploads of the idea.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function checkScreens($model)
    {
        $this->encodeScreens($model);
        if ($model->screens === null || $model->screens === '') {
            return true;
        }

        $uploadIds = $this->uploadIds($model->screens);
        if ($uploadIds === null) {
            return $this->reject($model, 'screens must be a JSON array of objects with an uploadId.');
        }

        $missing = array_diff($uploadIds, $this->findIdeaUploadIds((string) $model->ideaId, $uploadIds));
        if (!empty($missing)) {
            return $this->reject($model, 'screens reference uploads that do not belong to the idea: ' . implode(', ', $missing));
        }

        return true;
    }

    /**
     * The distinct upload ids of a screens JSON text, or null when it is not
     * an array of objects that each carry a non-empty uploadId.
     *
     * @param  string $screens
     * @return array|null
     */
    public function uploadIds($screens)
    {
        // Decoded without assoc so a JSON object stays an object and is not
        // mistaken for a list (e.g. {"0":{...}}).
        $decoded = json_decode((string) $screens);
        if (!is_array($decoded)) {
            return null;
        }

        $uploadIds = [];
        foreach ($decoded as $screen) {
            if (!is_object($screen) || !isset($screen->uploadId)
                || !is_string($screen->uploadId) || $screen->uploadId === ''
            ) {
                return null;
            }
            $uploadIds[] = $screen->uploadId;
        }

        return array_values(array_unique($uploadIds));
    }

    /**
     * The given upload ids that are uploads related to the idea.
     *
     * @param  string $ideaId
     * @param  array  $uploadIds
     * @return array
     */
    protected function findIdeaUploadIds($ideaId, array $uploadIds)
    {
        if (empty($uploadIds)) {
            return [];
        }

        $uploads = Upload::find([
            'columns' => 'id',
            'conditions' => 'relatedTo = :relatedTo: AND relatedId = :ideaId: AND id IN ({ids:array})',
            'bind' => [
                'relatedTo' => self::RELATED_TO,
                'ideaId' => $ideaId,
                'ids' => $uploadIds,
            ],
        ]);

        $found = [];
        foreach ($uploads as $upload) {
            $found[] = (string) $upload->id;
        }
        return $found;
    }

    /**
     * Add a validation message and cancel the save.
     *
     * @param  ModelInterface $model
     * @param  string         $message
     * @return bool
     */
    private function reject($model, $message)
    {
        $model->appendMessage(new Message($message, 'screens', 'InvalidValue'));
        return false;
    }
}
