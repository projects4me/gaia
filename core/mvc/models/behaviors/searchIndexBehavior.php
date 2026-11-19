<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Search\SearchBlock;
use Gaia\Libraries\Search\SemanticText;
use Gaia\MVC\REST\Controllers\Components\Events\SearchLiveEvents;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * This behavior keeps a searchable record's canonical semantic text and its
 * hash current, in the same write as the change, and tells Ares when the text
 * changed. SearchBlock attaches it to every model whose search block has
 * semantic fields, so every save path (REST, MCP, workflows, CLI) is covered.
 *
 * Before a create, and before an update that changed a semantic field, it
 * sets searchSemanticText and searchSemanticHash. After the save it publishes
 * search.semantic.changed when the hash changed. The embedding columns are not
 * written by the ORM, so the indexer's write never triggers it.
 *
 * Publishing is a side effect: an error is logged and never fails the save.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class searchIndexBehavior extends Behavior implements BehaviorInterface
{
    /**
     * @var SearchLiveEvents|null
     */
    private $events;

    /**
     * @param SearchLiveEvents|null $events Injected in tests.
     */
    public function __construct(?SearchLiveEvents $events = null)
    {
        $this->events = $events;
    }

    /**
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        $block = $this->blockOf($model);
        if ($block === null) {
            return;
        }

        if ($eventType === 'beforeCreate') {
            $this->stamp($model, $block);
        } elseif ($eventType === 'beforeUpdate' && $this->semanticChanged($model, $block)) {
            if ($this->loadedFully($model, $block)) {
                $this->stamp($model, $block);
            } else {
                $this->clear($model);
            }
        } elseif ($eventType === 'afterCreate') {
            $this->publish($model, $block);
        } elseif ($eventType === 'afterUpdate' && $this->hashChanged($model)) {
            $this->publish($model, $block);
        }
    }

    /**
     * The model's search block, or null when it has no semantic fields.
     *
     * @param  ModelInterface $model
     * @return array|null
     */
    private function blockOf($model)
    {
        $metadata = $model->getMetaData();
        $block = isset($metadata[SearchBlock::KEY]) ? $metadata[SearchBlock::KEY] : null;

        return is_array($block) && !empty($block['semantic']) ? $block : null;
    }

    /**
     * Sets the semantic text and hash from the record's values. When the text
     * cannot be built both are cleared, so the reindex picks the record up.
     *
     * @param  ModelInterface $model
     * @param  array          $block
     * @return void
     */
    private function stamp($model, array $block)
    {
        global $logger;

        try {
            $text = SemanticText::forBlock($block)->text($model);
            $model->{SearchBlock::SEMANTIC_TEXT} = $text;
            $model->{SearchBlock::SEMANTIC_HASH} = SemanticText::hashOf($text);
        } catch (\Throwable $e) {
            $this->clear($model);
            if ($logger) {
                $logger->error('Gaia.Search.searchIndexBehavior: could not build the semantic text: ' . $e->getMessage());
            }
        }
    }

    /**
     * Clears the semantic text and hash, so the reindex builds them again.
     *
     * @param  ModelInterface $model
     * @return void
     */
    private function clear($model)
    {
        $model->{SearchBlock::SEMANTIC_TEXT} = null;
        $model->{SearchBlock::SEMANTIC_HASH} = null;
    }

    /**
     * Whether every semantic field was loaded: a model read with some of its
     * columns would build the text from the missing ones as empty.
     *
     * @param  ModelInterface $model
     * @param  array          $block
     * @return bool
     */
    private function loadedFully($model, array $block)
    {
        $snapshot = $model->getSnapshotData();
        if (!is_array($snapshot) || empty($snapshot)) {
            return true;
        }
        foreach ($block['semantic'] as $field) {
            if (!array_key_exists($field, $snapshot) && !isset($model->{$field})) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether an update changed a semantic field. Before the update the
     * snapshot holds the loaded values; without one the fields count as
     * changed (stamping is idempotent).
     *
     * @param  ModelInterface $model
     * @param  array          $block
     * @return bool
     */
    private function semanticChanged($model, array $block)
    {
        $snapshot = $model->getSnapshotData();
        if (!is_array($snapshot) || empty($snapshot)) {
            return true;
        }

        foreach ($block['semantic'] as $field) {
            $before = array_key_exists($field, $snapshot) ? $snapshot[$field] : null;
            $after = isset($model->{$field}) ? $model->{$field} : null;
            if (!self::sameValue($before, $after)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether two field values are the same as text; a value that is not
     * scalar counts as changed.
     *
     * @param  mixed $before
     * @param  mixed $after
     * @return bool
     */
    private static function sameValue($before, $after)
    {
        if (($before !== null && !is_scalar($before)) || ($after !== null && !is_scalar($after))) {
            return false;
        }

        return (string) $before === (string) $after;
    }

    /**
     * Whether the update changed the semantic hash. Saving refreshes the
     * snapshot before afterUpdate, so the stored hash is in the old one;
     * without it the hash counts as changed (Ares skips a text it embedded).
     *
     * @param  ModelInterface $model
     * @return bool
     */
    private function hashChanged($model)
    {
        $old = $model->getOldSnapshotData();
        if (!is_array($old) || empty($old)) {
            return true;
        }
        $before = isset($old[SearchBlock::SEMANTIC_HASH]) ? $old[SearchBlock::SEMANTIC_HASH] : null;
        $after = isset($model->{SearchBlock::SEMANTIC_HASH}) ? $model->{SearchBlock::SEMANTIC_HASH} : null;

        return !self::sameValue($before, $after);
    }

    /**
     * Tells Ares the record's semantic text changed, logging any error.
     *
     * @param  ModelInterface $model
     * @param  array          $block
     * @return void
     */
    private function publish($model, array $block)
    {
        global $logger;

        try {
            if (empty($model->{SearchBlock::SEMANTIC_HASH})) {
                return;
            }
            $this->getEvents()->publishSemanticChanged(
                $block['type'],
                strtolower($model->getModelName()),
                isset($model->id) ? $model->id : null
            );
        } catch (\Throwable $e) {
            if ($logger) {
                $logger->error('Gaia.Search.searchIndexBehavior: could not publish the change: ' . $e->getMessage());
            }
        }
    }

    /**
     * @return SearchLiveEvents
     */
    private function getEvents()
    {
        if ($this->events === null) {
            $this->events = new SearchLiveEvents();
        }

        return $this->events;
    }
}
