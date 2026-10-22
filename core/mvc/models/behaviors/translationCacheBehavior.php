<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Utils\TranslationCache;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * Drops a locale's cached translation document when one of its rows is created,
 * updated or deleted (a soft delete is an update), so the next
 * GET /translations/{locale} rebuilds it. A row moved to another locale drops
 * both caches.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\Models\Behaviors
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class translationCacheBehavior extends Behavior implements BehaviorInterface
{
    /**
     * @var TranslationCache|null
     */
    private $cache;

    /**
     * @param TranslationCache|null $cache Injected in tests; defaults to the configured directory.
     */
    public function __construct(TranslationCache $cache = null)
    {
        $this->cache = $cache;
    }

    /**
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (in_array($eventType, ['afterCreate', 'afterUpdate', 'afterDelete'], true)) {
            $this->forgetLocalesOf($model);
        }
    }

    /**
     * @param  ModelInterface $model
     * @return void
     */
    private function forgetLocalesOf($model)
    {
        $locales = [$model->locale];

        $snapshot = $model->hasSnapshotData() ? $model->getSnapshotData() : [];
        if (isset($snapshot['locale'])) {
            $locales[] = $snapshot['locale'];
        }

        foreach (array_unique($locales) as $locale) {
            $this->forget($locale);
        }
    }

    /**
     * The row is already saved, so a cache that cannot be dropped is logged, not fatal.
     *
     * @param  mixed $locale
     * @return void
     */
    private function forget($locale)
    {
        global $logger;

        try {
            if (!$this->getCache()->forget($locale) && $logger) {
                $logger->error("Could not delete the translation cache of locale {$locale}");
            }
        } catch (\InvalidArgumentException $e) {
            // Not a locale that can have a cache file.
        }
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
