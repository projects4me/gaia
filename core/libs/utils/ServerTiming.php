<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * Records named durations and counts and formats them as a Server-Timing header value.
 *
 * @class ServerTiming
 * @package Gaia\Libraries\Utils
 * @author Hammad Hassan <gollomer@gmail.com>
 * @license AGPLv3
 */
class ServerTiming
{
    /**
     * @var float[] seconds per metric name
     */
    private $durations = [];

    /**
     * @var int[] event count per metric name
     */
    private $counts = [];

    /**
     * @var string[] fixed description per metric name
     */
    private $descriptions = [];

    /**
     * Adds a duration in seconds to a metric and counts one more event.
     *
     * @param string $name metric name
     * @param float $seconds elapsed seconds
     */
    public function add($name, $seconds)
    {
        $this->durations[$name] = ($this->durations[$name] ?? 0.0) + $seconds;
        $this->counts[$name] = ($this->counts[$name] ?? 0) + 1;
    }

    /**
     * Sets a metric to a fixed duration (e.g. boot, total).
     *
     * @param string $name metric name
     * @param float $seconds elapsed seconds
     */
    public function set($name, $seconds)
    {
        $this->durations[$name] = $seconds;
    }

    /**
     * Makes sure a metric is reported, with zero duration if nothing was recorded yet.
     *
     * @param string $name metric name
     */
    public function ensure($name)
    {
        $this->durations[$name] = $this->durations[$name] ?? 0.0;
    }

    /**
     * Sets the description of a metric, overriding the count.
     *
     * @param string $name metric name
     * @param string $description text shown as desc
     */
    public function describe($name, $description)
    {
        $this->descriptions[$name] = $description;
    }

    /**
     * Formats the metrics as a Server-Timing value, durations in milliseconds.
     *
     * @param array $labels metric name => noun used with its count, e.g. ['db' => 'queries']
     * @return string
     */
    public function format(array $labels = [])
    {
        $entries = [];
        foreach ($this->durations as $name => $seconds) {
            $entry = sprintf('%s;dur=%.1f', $name, $seconds * 1000);
            $desc = $this->descriptions[$name] ?? null;
            if ($desc === null && isset($this->counts[$name], $labels[$name])) {
                $desc = $this->counts[$name] . ' ' . $labels[$name];
            }
            if ($desc !== null) {
                $entry .= ';desc="' . str_replace(['\\', '"'], ['\\\\', '\\"'], $desc) . '"';
            }
            $entries[] = $entry;
        }
        return implode(', ', $entries);
    }

    /**
     * @param string $name metric name
     * @return int
     */
    public function count($name)
    {
        return $this->counts[$name] ?? 0;
    }
}
