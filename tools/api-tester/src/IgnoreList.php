<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace ApiTester;

/**
 * What compare-reports treats as runtime noise: values that differ between two
 * runs of the same stack without the API having changed.
 *
 * - Keys whose value is ignored (types are still compared): tokens.
 * - Patterns masked inside every string value and object key: GUIDs, Gaia
 *   record ids (create_guid() makes 12-4-4-12 hex, not 8-4-4-4-12) and
 *   date-times. A masked string compares equal to any string with the same
 *   shape, so `/api/v1/issue/<guid>` still differs from `/api/v1/issues/<guid>`.
 * - The report's own runId (the fixture value `{runId}` that makes unique
 *   fields unique per run) is masked by ReportComparer, which knows it.
 *
 * Date-only values (`2026-01-31`) are not masked: fixtures set them, so they
 * are the same on every run.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package ApiTester
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IgnoreList
{
    /**
     * Object keys (regex) whose values are runtime secrets or tokens.
     */
    const DEFAULT_KEYS = [
        '/token/i',
    ];

    /**
     * Placeholder => regex masked inside strings and object keys.
     */
    const DEFAULT_MASKS = [
        '{gaiaId}' => '/(?<![0-9a-f])[0-9a-f]{12}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?![0-9a-f])/i',
        '{guid}' => '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
        '{datetime}' => '/\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?/',
    ];

    private $keys;
    private $masks;

    /**
     * @param array|null $keys key regexes; null for DEFAULT_KEYS
     * @param array|null $masks placeholder => regex; null for DEFAULT_MASKS
     */
    public function __construct(?array $keys = null, ?array $masks = null)
    {
        $this->keys = $keys === null ? self::DEFAULT_KEYS : $keys;
        $this->masks = $masks === null ? self::DEFAULT_MASKS : $masks;
    }

    /**
     * @param string $key
     * @return bool true when the value under this key is not compared
     */
    public function ignoresKey($key)
    {
        foreach ($this->keys as $pattern) {
            if (preg_match($pattern, (string) $key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $value
     * @return string the value with every runtime pattern replaced by its placeholder
     */
    public function mask($value)
    {
        foreach ($this->masks as $placeholder => $pattern) {
            $value = preg_replace($pattern, $placeholder, $value);
        }
        return $value;
    }
}
