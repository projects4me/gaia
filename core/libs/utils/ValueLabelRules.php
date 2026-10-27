<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * The rules a list value (an issue status or issue type name) and its
 * translated labels must follow.
 *
 * The value is the key of its labels (ValueLabels), so it is limited to what
 * fits a translation path and the columns that hold it (issue_statuses.name
 * and issues.status are 30 characters). Labels may be any non-empty text.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ValueLabelRules
{
    /**
     * Longest value a list value may have: the length of issue_statuses.name
     * and of issues.status, which copies it.
     *
     * @var int
     */
    public const VALUE_MAX_LENGTH = 30;

    /**
     * @var string
     */
    public const VALUE_PATTERN = '/^[a-z0-9_]+\z/';

    /**
     * The locale whose label every value must have.
     *
     * @var string
     */
    public const REQUIRED_LOCALE = 'en-us';

    /**
     * @var TranslationLocales
     */
    private $locales;

    /**
     * What the value names in messages, e.g. "status" or "type".
     *
     * @var string
     */
    private $noun;

    /**
     * @param TranslationLocales $locales
     * @param string             $noun    What the value names in messages.
     */
    public function __construct(TranslationLocales $locales, $noun)
    {
        $this->locales = $locales;
        $this->noun = (string) $noun;
    }

    /**
     * @param  string $noun What the value names in messages.
     * @return self
     */
    public static function fromConfig($noun)
    {
        return new self(TranslationLocales::fromConfig(), $noun);
    }

    /**
     * @param  mixed $value
     * @return string|null Why the value is invalid, or null when it is valid.
     */
    public function valueError($value)
    {
        if (!is_string($value) || !preg_match(self::VALUE_PATTERN, $value)) {
            return 'Value may only contain lowercase letters, digits and underscores.';
        }

        if (strlen($value) > self::VALUE_MAX_LENGTH) {
            return 'Value must be at most ' . self::VALUE_MAX_LENGTH . ' characters.';
        }

        return null;
    }

    /**
     * The value keys the labels, so it cannot change once the record exists.
     *
     * @param  string $currentValue
     * @param  mixed  $newValue
     * @return string|null Why the change is invalid, or null when the value is unchanged.
     */
    public function valueChangeError($currentValue, $newValue)
    {
        return $newValue === $currentValue ? null : 'The value of a ' . $this->noun . ' cannot be changed.';
    }

    /**
     * Labels are an object of locale => label. A new record needs the
     * REQUIRED_LOCALE label; an update may send only the labels it changes.
     *
     * @param  mixed $translations
     * @param  bool  $creating
     * @return string|null Why the labels are invalid, or null when they are valid.
     */
    public function translationsError($translations, $creating)
    {
        if ($translations === null && !$creating) {
            return null;
        }

        if (!is_array($translations)) {
            return 'Translations must be an object of locale => label.';
        }

        if ($creating && !array_key_exists(self::REQUIRED_LOCALE, $translations)) {
            return 'The ' . self::REQUIRED_LOCALE . ' label is required.';
        }

        foreach ($translations as $locale => $label) {
            if (!$this->locales->isSupported($locale)) {
                return 'Unsupported locale: ' . $locale . '. Supported: ' . implode(', ', $this->locales->all()) . '.';
            }
            if (!is_string($label) || trim($label) === '') {
                return 'The ' . $locale . ' label must be a non-empty string.';
            }
        }

        return null;
    }
}
