<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Tags;

/**
 * The text rules of a tag name (Tags D-001): surrounding whitespace is
 * trimmed, inner runs of whitespace become one space, the user's case is
 * kept, and the result is 1 to {@see TagName::MAX_LENGTH} characters long.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Tags
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TagName
{
    const MAX_LENGTH = 50;

    const EMPTY_NAME = 'A tag needs a name.';

    const TOO_LONG = 'A tag name can be at most 50 characters long.';

    /**
     * The name as it is stored.
     *
     * @param  mixed $raw
     * @return string
     */
    public static function normalise($raw)
    {
        $name = preg_replace('/\s+/u', ' ', (string) $raw);
        if ($name === null) {
            // Not valid UTF-8: fall back to ASCII whitespace.
            $name = preg_replace('/\s+/', ' ', (string) $raw);
        }

        return trim($name);
    }

    /**
     * Why a normalised name can't be stored, or null when it can.
     *
     * @param  string $name
     * @return string|null
     */
    public static function error($name)
    {
        if ($name === '') {
            return self::EMPTY_NAME;
        }
        if (mb_strlen($name, 'UTF-8') > self::MAX_LENGTH) {
            return self::TOO_LONG;
        }

        return null;
    }
}
