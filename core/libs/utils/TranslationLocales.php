<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * The locales Gaia serves translations for (config/translations.php).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TranslationLocales
{
    /**
     * @var string[]
     */
    private $locales;

    /**
     * @param string[] $locales
     */
    public function __construct(array $locales)
    {
        $this->locales = array_values(array_map('strval', $locales));
    }

    /**
     * @return self
     */
    public static function fromConfig()
    {
        global $settings;

        $locales = isset($settings['translations']['locales']) ? $settings['translations']['locales'] : [];
        if (is_object($locales) && method_exists($locales, 'toArray')) {
            $locales = $locales->toArray();
        }

        return new self((array) $locales);
    }

    /**
     * @return string[]
     */
    public function all()
    {
        return $this->locales;
    }

    /**
     * Exact match only, so the value is safe to use in a file name afterwards.
     *
     * @param  mixed $locale
     * @return bool
     */
    public function isSupported($locale)
    {
        return is_string($locale) && in_array($locale, $this->locales, true);
    }
}
