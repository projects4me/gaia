<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Libraries\Meta\Manager;

/**
 * The searchable models: looks up a search block by its type (the name in /search?types= and in search events)
 * or by its model, and makes sure no two models claim the same type.
 *
 * Each entry is the model's search block, as SearchBlock::apply left it, plus 'model'.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchableRegistry
{
    /**
     * @var Manager
     */
    private $metaManager;

    /**
     * @var array
     */
    private $modelNames;

    /**
     * Entries keyed by type, built on first use.
     *
     * @var array|null
     */
    private $byType = null;

    /**
     * Type of each searchable model, built on first use.
     *
     * @var array|null
     */
    private $typeByModel = null;

    /**
     * @param Manager $metaManager
     * @param array $modelNames The models to consider
     */
    public function __construct(Manager $metaManager, array $modelNames)
    {
        $this->metaManager = $metaManager;
        $this->modelNames = array_values($modelNames);
    }

    /**
     * This function returns the registry of every model in app/metadata/model.
     *
     * @param Manager $metaManager
     * @return self
     */
    public static function fromMetadata(Manager $metaManager)
    {
        $files = glob(APP_PATH . Manager::basePath . '/model/*.php') ?: array();
        sort($files);

        return new self($metaManager, array_map(function ($file) {
            return basename($file, '.php');
        }, $files));
    }

    /**
     * This function returns the search block registered for a type, or null.
     *
     * @param string $type
     * @return array|null
     */
    public function forType($type)
    {
        $this->load();

        return isset($this->byType[$type]) ? $this->byType[$type] : null;
    }

    /**
     * This function returns a model's search block, or null when the model is not searchable.
     *
     * @param string $model
     * @return array|null
     */
    public function forModel($model)
    {
        $this->load();

        return isset($this->typeByModel[$model]) ? $this->byType[$this->typeByModel[$model]] : null;
    }

    /**
     * This function returns the registered types, sorted.
     *
     * @return array
     */
    public function types()
    {
        $this->load();
        $types = array_keys($this->byType);
        sort($types);

        return $types;
    }

    /**
     * This function reads the search blocks of the models once.
     *
     * @return void
     * @throws \Gaia\Exception\Exception When two models use the same type
     */
    private function load()
    {
        if ($this->byType !== null) {
            return;
        }

        $byType = array();
        $typeByModel = array();
        foreach ($this->modelNames as $model) {
            $meta = $this->metaManager->getModelMeta($model);
            $block = isset($meta[SearchBlock::KEY]) ? $meta[SearchBlock::KEY] : null;
            if ($block === null) {
                continue;
            }
            $type = $block['type'];
            if (isset($byType[$type])) {
                throw new \Gaia\Exception\Exception(
                    "Search type '{$type}' is used by models {$byType[$type]['model']} and {$model}; it must be unique."
                );
            }
            $byType[$type] = array('model' => $model) + $block;
            $typeByModel[$model] = $type;
        }

        $this->byType = $byType;
        $this->typeByModel = $typeByModel;
    }
}
