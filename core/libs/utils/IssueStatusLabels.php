<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

use Gaia\MVC\Models\Issuestatus;
use Gaia\MVC\Models\Translation;

/**
 * The translated labels of issue statuses, stored as Translation rows at
 * views.app.issue.lists.status.{value}.
 *
 * Labels are keyed by the status value, not by the status: statuses with the
 * same value (a system status and its project copies, or the same custom value
 * in two projects) share one label per locale. Saving a label for one of them
 * changes it for all, and the label is only deleted with the last status that
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
class IssueStatusLabels
{
    /**
     * @var string
     */
    public const PATH_PREFIX = 'views.app.issue.lists.status.';

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
     * @param TranslationCache|null $cache Defaults to the configured directory.
     */
    public function __construct(TranslationCache $cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * @param  string $value
     * @return string
     */
    public static function pathFor($value)
    {
        return self::PATH_PREFIX . $value;
    }

    /**
     * @param  string[] $values Status values
     * @return array value => [locale => label], for the values that have labels.
     */
    public function labelsFor(array $values)
    {
        $paths = [];
        foreach (array_unique(array_map('strval', $values)) as $value) {
            $paths[self::pathFor($value)] = $value;
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
     * Create or update the labels of a status value. A row deleted earlier for
     * the same locale and path is restored, since (locale, path) is unique.
     *
     * @param  string $value  Status value
     * @param  array  $labels locale => label, validated by IssueStatusRules
     * @return void
     * @throws \Gaia\Exception\Exception When a row cannot be saved.
     */
    public function save($value, array $labels)
    {
        $path = self::pathFor($value);

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
                    "Could not save the {$locale} label of status {$value}: " . implode(' ', $this->messagesOf($row))
                );
            }
            $this->writtenLocales[(string) $locale] = true;
        }
    }

    /**
     * Delete the labels of a status value unless another status still uses it.
     *
     * @param  string $value Status value
     * @return void
     * @throws \Gaia\Exception\Exception When a row cannot be deleted.
     */
    public function deleteUnused($value)
    {
        $inUse = Issuestatus::count([
            'conditions' => 'name = :name: AND deleted = :deleted:',
            'bind' => ['name' => (string) $value, 'deleted' => 0],
        ]);
        if ($inUse > 0) {
            return;
        }

        $rows = Translation::find([
            'conditions' => 'path = :path: AND deleted = :deleted:',
            'bind' => ['path' => self::pathFor($value), 'deleted' => 0],
        ]);
        foreach ($rows as $row) {
            if (!$row->delete()) {
                throw new \Gaia\Exception\Exception(
                    "Could not delete the {$row->locale} label of status {$value}: " . implode(' ', $this->messagesOf($row))
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
