<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Query;

/**
 * Turns a query answer into terse text, or JSON.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Query
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Renderer
{
    /**
     * @param array $answer see Query::run
     * @return string
     */
    public static function text(array $answer): string
    {
        $lines = [];
        foreach ($answer as $label => $value) {
            if ($label === 'title') {
                $lines[] = $value;
            } elseif (!is_array($value)) {
                $lines[] = "{$label}: {$value}";
            } elseif ($value === []) {
                $lines[] = "{$label}: -";
            } else {
                $lines[] = "{$label}:";
                foreach ($value as $line) {
                    $lines[] = '  ' . $line;
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array $answer
     * @return string
     */
    public static function json(array $answer): string
    {
        return json_encode($answer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
