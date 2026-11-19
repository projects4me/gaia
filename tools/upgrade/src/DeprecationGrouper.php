<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Upgrade;

/**
 * Reads PHP error-log lines and groups the deprecations and warnings by message pattern and by file:line.
 *
 * Accepts the PHP log format ("[date] PHP Deprecated:  message in /file.php on line 12", date optional);
 * lines that are not diagnostics are skipped. Entries under /vendor/ count as vendor, the rest as Gaia.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Upgrade
 * @category Tools
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DeprecationGrouper
{
    const LINE = '/^(?:\[[^\]]*\]\s+)?PHP (Deprecated|Warning|Notice):\s+(.*) in (\S+) on line (\d+)\s*$/';

    /** @var array[] one record per diagnostic: level, message, pattern, file, line, origin */
    private $entries = [];

    /**
     * Adds every diagnostic found in the given log lines.
     *
     * @param iterable $lines
     * @return int the number of diagnostics added
     */
    public function addLines($lines)
    {
        $added = 0;
        foreach ($lines as $line) {
            if (!preg_match(self::LINE, rtrim($line), $m)) {
                continue;
            }
            $this->entries[] = [
                'level' => $m[1],
                'message' => $m[2],
                'pattern' => self::pattern($m[2]),
                'file' => $m[3],
                'line' => (int) $m[4],
                'origin' => strpos($m[3], '/vendor/') !== false ? 'vendor' : 'gaia',
            ];
            $added++;
        }
        return $added;
    }

    /**
     * Reduces a message to its pattern: drops the Class::method() prefix, variable names, quoted values and numbers.
     *
     * @param string $message
     * @return string
     */
    public static function pattern($message)
    {
        $message = preg_replace('/^[\w\\\\]+::\w+\(\): /', '', $message);
        $message = preg_replace('/\$\w+/', '$X', $message);
        $message = preg_replace('/\'[^\']*\'|"[^"]*"/', "'X'", $message);
        return preg_replace('/\d+/', 'N', $message);
    }

    /**
     * Groups the entries of one origin by level and message pattern, most frequent first.
     *
     * @param string $origin gaia or vendor
     * @return array[] each with level, pattern, count, example (message) and locations (file:line => count)
     */
    public function byPattern($origin)
    {
        $groups = [];
        foreach ($this->entries as $e) {
            if ($e['origin'] !== $origin) {
                continue;
            }
            $key = $e['level'] . '|' . $e['pattern'];
            if (!isset($groups[$key])) {
                $groups[$key] = ['level' => $e['level'], 'pattern' => $e['pattern'], 'count' => 0,
                    'example' => $e['message'], 'locations' => []];
            }
            $groups[$key]['count']++;
            $location = $e['file'] . ':' . $e['line'];
            $groups[$key]['locations'][$location] = ($groups[$key]['locations'][$location] ?? 0) + 1;
        }
        $groups = array_values($groups);
        usort($groups, function ($a, $b) {
            return [$b['count'], $a['pattern']] <=> [$a['count'], $b['pattern']];
        });
        return $groups;
    }

    /**
     * @return array counts: total, gaia, vendor
     */
    public function totals()
    {
        $totals = ['total' => count($this->entries), 'gaia' => 0, 'vendor' => 0];
        foreach ($this->entries as $e) {
            $totals[$e['origin']]++;
        }
        return $totals;
    }
}
