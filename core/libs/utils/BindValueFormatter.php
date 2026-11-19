<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * Formats values for log lines and text output without "Array to string conversion".
 *
 * Arrays (and objects) become JSON, null becomes "NULL", booleans "true"/"false";
 * everything else is cast to string.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BindValueFormatter
{
    /**
     * @param  mixed $value
     * @return string
     */
    public static function format($value)
    {
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            return $json === false ? '[unencodable]' : $json;
        }
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        return (string) $value;
    }

    /**
     * @param  array  $values
     * @param  string $glue
     * @return string
     */
    public static function join(array $values, $glue = ', ')
    {
        return implode($glue, array_map([self::class, 'format'], $values));
    }
}
