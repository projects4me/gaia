<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * Hands out the translation document of a supported locale: the cached file when
 * there is one, otherwise a document built from the translations table, which is
 * then cached for the next request.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TranslationCatalog
{
    /**
     * @var TranslationLocales
     */
    private $locales;

    /**
     * @var TranslationCache
     */
    private $cache;

    /**
     * @var TranslationSource
     */
    private $source;

    /**
     * @param TranslationLocales $locales
     * @param TranslationCache   $cache
     * @param TranslationSource  $source
     */
    public function __construct(TranslationLocales $locales, TranslationCache $cache, TranslationSource $source)
    {
        $this->locales = $locales;
        $this->cache = $cache;
        $this->source = $source;
    }

    /**
     * @return self
     */
    public static function fromConfig()
    {
        return new self(TranslationLocales::fromConfig(), TranslationCache::fromConfig(), new TranslationSource());
    }

    /**
     * @return string[]
     */
    public function locales()
    {
        return $this->locales->all();
    }

    /**
     * @param  mixed $locale
     * @return string|null The JSON document, or null when the locale is not supported.
     */
    public function document($locale)
    {
        if (!$this->locales->isSupported($locale)) {
            return null;
        }

        $cached = $this->cache->read($locale);
        if ($cached !== null) {
            return $cached;
        }

        $version = $this->cache->version($locale);
        $document = json_encode(
            TranslationTree::build($this->source->labelsFor($locale)),
            JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        // Not cached when a row changed while it was built; a cache that cannot be
        // written only costs a rebuild on the next request.
        $this->cache->write($locale, $document, $version);

        return $document;
    }
}
