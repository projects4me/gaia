<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

use Gaia\MVC\Models\Issuestatus;
use Gaia\MVC\Models\Issuetype;
use Gaia\MVC\Models\Translation;

/**
 * The translated labels of a list's values (issue statuses, issue types),
 * stored as Translation rows at {pathPrefix}{value}.
 *
 * Labels are keyed by the value, not by the record: records with the same
 * value (a system record and its project copies, or the same custom value in
 * two projects) share one label per locale. Saving a label for one of them
 * changes it for all, and the label is only deleted with the last record that
 * uses the value.
 *
 * Every row written drops its locale's cache (translationCacheBehavior), but
 * that happens before the surrounding transaction commits, so a document
 * rebuilt meanwhile could still hold the old label. Call
 * forgetWrittenLocales() after the commit to drop those caches again.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ValueLabels
{
    /**
     * @var string
     */
    public const STATUS_PATH_PREFIX = 'views.app.issue.lists.status.';

    /**
     * @var string
     */
    public const TYPE_PATH_PREFIX = 'views.app.issue.lists.type.';

    /**
     * @var string
     */
    private $pathPrefix;

    /**
     * Model whose `name` holds the values (it has `system` and `deleted` fields).
     *
     * @var string
     */
    private $modelClass;

    /**
     * What the value names in messages, e.g. "status".
     *
     * @var string
     */
    private $noun;

    /**
     * Locales with a row written since the last forgetWrittenLocales(), as keys.
     *
     * @var array
     */
    private $writtenLocales = [];

    /**
     * @var TranslationCache|null
     */
    private $cache;

    /**
     * @param string                $pathPrefix Translation path the value is appended to.
     * @param string                $modelClass Model whose `name` holds the values.
     * @param string                $noun       What the value names in messages.
     * @param TranslationCache|null $cache      Defaults to the configured directory.
     */
    public function __construct($pathPrefix, $modelClass, $noun, TranslationCache $cache = null)
    {
        $this->pathPrefix = (string) $pathPrefix;
        $this->modelClass = (string) $modelClass;
        $this->noun = (string) $noun;
        $this->cache = $cache;
    }

    /**
     * @param  TranslationCache|null $cache
     * @return self The labels of issue status values.
     */
    public static function forIssueStatuses(TranslationCache $cache = null)
    {
        return new self(self::STATUS_PATH_PREFIX, Issuestatus::class, 'status', $cache);
    }

    /**
     * @param  TranslationCache|null $cache
     * @return self The labels of issue type values.
     */
    public static function forIssueTypes(TranslationCache $cache = null)
    {
        return new self(self::TYPE_PATH_PREFIX, Issuetype::class, 'type', $cache);
    }

    /**
     * @return string What the value names in messages, e.g. "status".
     */
    public function noun()
    {
        return $this->noun;
    }

    /**
     * @return string The short name of the model whose `name` holds the values, e.g. "Issuestatus".
     */
    public function modelName()
    {
        $parts = explode('\\', $this->modelClass);

        return end($parts);
    }

    /**
     * The value of a stored record, or null when there is none.
     *
     * @param  mixed $id
     * @return string|null
     */
    public function valueOf($id)
    {
        if (!is_scalar($id)) {
            return null;
        }

        $modelClass = $this->modelClass;
        $record = $modelClass::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => (string) $id],
        ]);

        return $record ? (string) $record->name : null;
    }

    /**
     * @param  string $value
     * @return string
     */
    public function pathFor($value)
    {
        return $this->pathPrefix . $value;
    }

    /**
     * @param  string[] $values
     * @return array value => [locale => label], for the values that have labels.
     */
    public function labelsFor(array $values)
    {
        $paths = [];
        foreach (array_unique(array_map('strval', $values)) as $value) {
            $paths[$this->pathFor($value)] = $value;
        }
        if (empty($paths)) {
            return [];
        }

        $rows = Translation::find([
            'conditions' => 'path IN ({paths:array}) AND deleted = :deleted:',
            'bind' => ['paths' => array_keys($paths), 'deleted' => 0],
            'order' => 'locale',
        ]);

        $labels = [];
        foreach ($rows as $row) {
            $labels[$paths[$row->path]][$row->locale] = $row->value;
        }

        return $labels;
    }

    /**
     * Create or update the labels of a value. A row deleted earlier for the
     * same locale and path is restored, since (locale, path) is unique.
     *
     * @param  string $value
     * @param  array  $labels locale => label, validated by ValueLabelRules
     * @return void
     * @throws \Gaia\Exception\Exception When a row cannot be saved.
     */
    public function save($value, array $labels)
    {
        $path = $this->pathFor($value);

        foreach ($labels as $locale => $label) {
            $row = Translation::findFirst([
                'conditions' => 'locale = :locale: AND path = :path:',
                'bind' => ['locale' => (string) $locale, 'path' => $path],
            ]);

            if ($row) {
                $row->value = $label;
                $row->deleted = 0;
            } else {
                $row = new Translation();
                $row->id = create_guid();
                $row->locale = (string) $locale;
                $row->path = $path;
                $row->value = $label;
                $row->deleted = 0;
            }

            if (!$row->save()) {
                throw new \Gaia\Exception\Exception(
                    "Could not save the {$locale} label of {$this->noun} {$value}: " . implode(' ', $this->messagesOf($row))
                );
            }
            $this->writtenLocales[(string) $locale] = true;
        }
    }

    /**
     * Whether an active system record uses the value, so its labels are
     * system labels too.
     *
     * @param  string $value
     * @return bool
     */
    public function isSystemValue($value)
    {
        $modelClass = $this->modelClass;

        return $modelClass::count([
            'conditions' => 'name = :name: AND system = :system: AND deleted = :deleted:',
            'bind' => ['name' => (string) $value, 'system' => 1, 'deleted' => 0],
        ]) > 0;
    }

    /**
     * Delete the labels of a value unless another record still uses it.
     *
     * @param  string $value
     * @return void
     * @throws \Gaia\Exception\Exception When a row cannot be deleted.
     */
    public function deleteUnused($value)
    {
        $modelClass = $this->modelClass;
        $inUse = $modelClass::count([
            'conditions' => 'name = :name: AND deleted = :deleted:',
            'bind' => ['name' => (string) $value, 'deleted' => 0],
        ]);
        if ($inUse > 0) {
            return;
        }

        $rows = Translation::find([
            'conditions' => 'path = :path: AND deleted = :deleted:',
            'bind' => ['path' => $this->pathFor($value), 'deleted' => 0],
        ]);
        foreach ($rows as $row) {
            if (!$row->delete()) {
                throw new \Gaia\Exception\Exception(
                    "Could not delete the {$row->locale} label of {$this->noun} {$value}: " . implode(' ', $this->messagesOf($row))
                );
            }
            $this->writtenLocales[(string) $row->locale] = true;
        }
    }

    /**
     * Drop the cache of every locale written since the last call.
     *
     * @return void
     */
    public function forgetWrittenLocales()
    {
        global $logger;

        foreach (array_keys($this->writtenLocales) as $locale) {
            if (!$this->getCache()->forget($locale) && $logger) {
                $logger->error("Could not delete the translation cache of locale {$locale}");
            }
        }
        $this->writtenLocales = [];
    }

    /**
     * @param  Translation $row
     * @return string[]
     */
    private function messagesOf($row)
    {
        return array_map(function ($message) {
            return $message->getMessage();
        }, $row->getMessages());
    }

    /**
     * @return TranslationCache
     */
    private function getCache()
    {
        if ($this->cache === null) {
            $this->cache = TranslationCache::fromConfig();
        }

        return $this->cache;
    }
}
