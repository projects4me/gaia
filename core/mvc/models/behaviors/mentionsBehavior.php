<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Mentions\MentionRecorder;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior records the users mentioned in a record's text fields, named
 * in the model's metadata as 'mentions' => ['fields' => [...]]. It runs after
 * a create, and after an update that changed one of those fields, so every
 * save path (REST, collection PATCH, workflows) records mentions.
 *
 * Mentions are a side effect: an error while recording them is logged and
 * never fails the save.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class mentionsBehavior extends Behavior implements BehaviorInterface
{
    /**
     * @var MentionRecorder|null
     */
    private $recorder;

    /**
     * @param MentionRecorder|null $recorder Injected in tests.
     */
    public function __construct(MentionRecorder $recorder = null)
    {
        $this->recorder = $recorder;
    }

    /**
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if ($eventType === 'afterCreate') {
            $this->record($model, false);
        } elseif ($eventType === 'afterUpdate') {
            $this->record($model, true);
        }
    }

    /**
     * The fields of the model that hold mentions.
     *
     * @param  ModelInterface $model
     * @return array
     */
    private function fieldsOf($model)
    {
        $metadata = $model->getMetaData();
        $fields = isset($metadata['mentions']['fields']) ? $metadata['mentions']['fields'] : [];

        return is_array($fields) ? array_values($fields) : [];
    }

    /**
     * Hands the record to the recorder, logging any error.
     *
     * @param  ModelInterface $model
     * @param  bool           $updated
     * @return void
     */
    private function record($model, $updated)
    {
        global $logger;

        try {
            $fields = $this->fieldsOf($model);
            if (empty($fields) || ($updated && !$this->changed($model, $fields))) {
                return;
            }
            $this->getRecorder()->record($model, $fields, $this->actorId());
        } catch (\Throwable $e) {
            if ($logger) {
                $logger->error('Gaia.Mentions.mentionsBehavior: could not record mentions: ' . $e->getMessage());
            }
        }
    }

    /**
     * Whether an update changed one of the fields. Saving refreshes the
     * snapshot before afterUpdate, so the pre-update values are in the old
     * one; without it the fields count as changed (recording is idempotent).
     *
     * @param  ModelInterface $model
     * @param  array          $fields
     * @return bool
     */
    private function changed($model, array $fields)
    {
        $old = $model->getOldSnapshotData();
        if (!is_array($old) || empty($old)) {
            return true;
        }

        foreach ($fields as $field) {
            $before = array_key_exists($field, $old) ? $old[$field] : null;
            $after = isset($model->{$field}) ? $model->{$field} : null;
            if ((string) $before !== (string) $after) {
                return true;
            }
        }
        return false;
    }

    /**
     * The id of the user who saved the record.
     *
     * @return string|null
     */
    private function actorId()
    {
        global $currentUser;

        return isset($currentUser->id) ? (string) $currentUser->id : null;
    }

    /**
     * @return MentionRecorder
     */
    private function getRecorder()
    {
        if ($this->recorder === null) {
            $this->recorder = new MentionRecorder();
        }

        return $this->recorder;
    }
}
