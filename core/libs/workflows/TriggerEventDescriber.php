<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Describes the triggers for the workflow editor: each event's module and
 * label, the fields it watches, and the payload paths with their types that
 * filters and expressions can use. The schedule trigger is described last.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TriggerEventDescriber
{
    /**
     * @var TriggerEventCatalog
     */
    private $catalog;

    /**
     * @var ModelFieldTypes
     */
    private $fieldTypes;

    /**
     * @param TriggerEventCatalog|null $catalog
     * @param ModelFieldTypes|null     $fieldTypes
     */
    public function __construct(TriggerEventCatalog $catalog = null, ModelFieldTypes $fieldTypes = null)
    {
        $this->catalog = $catalog ?: new TriggerEventCatalog();
        $this->fieldTypes = $fieldTypes ?: new ModelFieldTypes();
    }

    /**
     * Every trigger, described.
     *
     * @return array[] Each: name, label, module, on, watchedFields, fields
     *                 (a list of ['path' => ..., 'type' => ...]).
     */
    public function all()
    {
        $triggers = [];
        foreach ($this->catalog->names() as $name) {
            $triggers[] = $this->event($name);
        }
        $triggers[] = [
            'name' => TriggerEventCatalog::SCHEDULE,
            'label' => 'Schedule',
            'module' => null,
            'on' => null,
            'watchedFields' => [],
            'fields' => self::paths(TriggerPayload::SCHEDULE_FIELDS),
        ];

        return $triggers;
    }

    /**
     * @param  string $name
     * @return array
     */
    private function event($name)
    {
        $event = $this->catalog->definition($name);
        $types = $this->fieldTypes->forModel($event['model']);
        $key = TriggerPayload::recordKey($event['model']);

        $paths = [];
        foreach ($types as $field => $type) {
            $paths["{$key}.{$field}"] = $type;
        }
        $changed = [];
        if ($event['on'] === TriggerEventCatalog::ON_UPDATE) {
            $changed = $event['fields']
                ?: array_keys(array_diff_key($types, array_flip(TriggerEventCatalog::BOOKKEEPING_FIELDS)));
        }
        foreach ($changed as $field) {
            if (isset($types[$field])) {
                $paths["changes.{$field}.before"] = $types[$field];
                $paths["changes.{$field}.after"] = $types[$field];
            }
        }

        return [
            'name' => $name,
            'label' => $event['label'],
            'module' => $event['model'],
            'on' => $event['on'],
            'watchedFields' => $event['fields'],
            'fields' => self::paths(array_merge(TriggerPayload::COMMON_FIELDS, $paths)),
        ];
    }

    /**
     * @param  array $types path => type
     * @return array[]
     */
    private static function paths(array $types)
    {
        $paths = [];
        foreach ($types as $path => $type) {
            $paths[] = ['path' => $path, 'type' => $type];
        }

        return $paths;
    }
}
