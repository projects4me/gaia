<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * Reads, writes and drops the cached translation document of a locale
 * ({cacheDirectory}/{locale}.json).
 *
 * Callers validate the locale against TranslationLocales first; this class
 * still refuses any locale that is not a plain tag (e.g. en-us), so a locale
 * can never point outside the cache directory.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TranslationCache
{
    /**
     * A locale tag: letters and digits in hyphen-separated parts.
     */
    private const LOCALE_PATTERN = '/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/';

    /**
     * @var string
     */
    private $directory;

    /**
     * @param string $directory
     */
    public function __construct($directory)
    {
        $this->directory = rtrim((string) $directory, '/\\');
    }

    /**
     * @return self
     */
    public static function fromConfig()
    {
        global $settings;

        if (empty($settings['translations']['cacheDirectory'])) {
            throw new \RuntimeException('translations.cacheDirectory is not configured (config/translations.php).');
        }

        return new self($settings['translations']['cacheDirectory']);
    }

    /**
     * @param  string $locale
     * @return string|null The cached document, or null when there is none.
     */
    public function read($locale)
    {
        $file = $this->fileFor($locale);
        if (!is_file($file)) {
            return null;
        }

        $content = file_get_contents($file);
        return $content === false ? null : $content;
    }

    /**
     * Changes every time the locale's cache is forgotten; '' until the first time.
     *
     * @param  string $locale
     * @return string
     */
    public function version($locale)
    {
        $file = $this->versionFileFor($locale);
        $version = is_file($file) ? file_get_contents($file) : '';

        return $version === false ? '' : $version;
    }

    /**
     * Written to a temporary file and renamed, so readers never see half a document.
     *
     * Pass the version() read before the document was built: when the cache was
     * forgotten meanwhile, the document may be stale and is not written.
     *
     * @param  string      $locale
     * @param  string      $document
     * @param  string|null $builtAtVersion
     * @return bool
     */
    public function write($locale, $document, $builtAtVersion = null)
    {
        $file = $this->fileFor($locale);

        if (!$this->ensureDirectory()) {
            return false;
        }

        $temporary = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($temporary, $document) === false) {
            return false;
        }

        if (($builtAtVersion !== null && $this->version($locale) !== $builtAtVersion) || !@rename($temporary, $file)) {
            @unlink($temporary);
            return false;
        }

        return true;
    }

    /**
     * @param  string $locale
     * @return bool True when no cached document is left.
     */
    public function forget($locale)
    {
        $file = $this->fileFor($locale);

        if ($this->ensureDirectory()) {
            @file_put_contents($this->versionFileFor($locale), bin2hex(random_bytes(8)));
        }

        return !is_file($file) || @unlink($file);
    }

    /**
     * @param  string $locale
     * @return string
     * @throws \InvalidArgumentException
     */
    private function fileFor($locale)
    {
        if (!is_string($locale) || !preg_match(self::LOCALE_PATTERN, $locale)) {
            throw new \InvalidArgumentException('Invalid locale.');
        }

        return $this->directory . DIRECTORY_SEPARATOR . $locale . '.json';
    }

    /**
     * @return bool
     */
    private function ensureDirectory()
    {
        return is_dir($this->directory) || @mkdir($this->directory, 0775, true) || is_dir($this->directory);
    }

    /**
     * @param  string $locale
     * @return string
     */
    private function versionFileFor($locale)
    {
        return substr($this->fileFor($locale), 0, -strlen('.json')) . '.version';
    }
}
