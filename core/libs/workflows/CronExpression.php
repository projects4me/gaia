<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * A five-field cron expression (minute, hour, day of month, month, day of
 * week) and whether a minute matches it. Each field takes `*`, a number, a
 * range `a-b`, a step (`*` or `a` or `a-b`, then `/n`), or a comma list of those. Day of
 * week runs 0-7, where 0 and 7 are Sunday. As in cron, when both day of
 * month and day of week are restricted, a day matching either one matches.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CronExpression
{
    /**
     * Each field's name and allowed range, in expression order.
     *
     * @var array
     */
    private const FIELDS = [
        ['minute', 0, 59],
        ['hour', 0, 23],
        ['day of month', 1, 31],
        ['month', 1, 12],
        ['day of week', 0, 7],
    ];

    private const DAY_OF_MONTH = 2;
    private const DAY_OF_WEEK = 4;

    /**
     * The allowed values of each field, as value => true.
     *
     * @var array[]
     */
    private $values;

    /**
     * Which fields are `*`, so they don't restrict the day.
     *
     * @var bool[]
     */
    private $any;

    /**
     * @param  string $expression
     * @throws \InvalidArgumentException When the expression is not valid
     */
    public function __construct($expression)
    {
        $parts = is_string($expression) ? preg_split('/\s+/', trim($expression)) : [];
        if (count($parts) !== count(self::FIELDS)) {
            throw new \InvalidArgumentException(
                'must have five fields: minute, hour, day of month, month and day of week.'
            );
        }

        foreach (self::FIELDS as $i => list($name, $min, $max)) {
            $this->any[$i] = $parts[$i] === '*';
            $this->values[$i] = $this->parseField($parts[$i], $name, $min, $max);
        }
        if (isset($this->values[self::DAY_OF_WEEK][7])) {
            $this->values[self::DAY_OF_WEEK][0] = true;
        }
    }

    /**
     * Why the expression is not valid, or null when it is.
     *
     * @param  mixed $expression
     * @return string|null
     */
    public static function error($expression)
    {
        try {
            new self($expression);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Whether the minute of $at matches the expression.
     *
     * @param  \DateTimeInterface $at
     * @return bool
     */
    public function matches(\DateTimeInterface $at)
    {
        $minute = (int) $at->format('i');
        $hour = (int) $at->format('G');
        $month = (int) $at->format('n');

        return isset($this->values[0][$minute], $this->values[1][$hour], $this->values[3][$month])
            && $this->matchesDay((int) $at->format('j'), (int) $at->format('w'));
    }

    /**
     * @param  int $dayOfMonth
     * @param  int $dayOfWeek
     * @return bool
     */
    private function matchesDay($dayOfMonth, $dayOfWeek)
    {
        $byMonth = isset($this->values[self::DAY_OF_MONTH][$dayOfMonth]);
        $byWeek = isset($this->values[self::DAY_OF_WEEK][$dayOfWeek]);
        if (!$this->any[self::DAY_OF_MONTH] && !$this->any[self::DAY_OF_WEEK]) {
            return $byMonth || $byWeek;
        }

        return $byMonth && $byWeek;
    }

    /**
     * @param  string $field
     * @param  string $name
     * @param  int    $min
     * @param  int    $max
     * @return array
     */
    private function parseField($field, $name, $min, $max)
    {
        $values = [];
        foreach (explode(',', $field) as $part) {
            if (!preg_match('/^(\*|(\d+)(?:-(\d+))?)(?:\/(\d+))?$/', $part, $m)) {
                throw new \InvalidArgumentException("has an invalid {$name} field '{$field}'.");
            }
            $hasStep = isset($m[4]) && $m[4] !== '';
            $from = $m[1] === '*' ? $min : (int) $m[2];
            if ($m[1] === '*') {
                $to = $max;
            } elseif (isset($m[3]) && $m[3] !== '') {
                $to = (int) $m[3];
            } else {
                // `a/n` runs from a to the end of the range
                $to = $hasStep ? $max : $from;
            }
            $step = $hasStep ? (int) $m[4] : 1;
            if ($from < $min || $to > $max || $from > $to || $step < 1) {
                throw new \InvalidArgumentException("has a {$name} out of range {$min}-{$max} in '{$field}'.");
            }
            for ($value = $from; $value <= $to; $value += $step) {
                $values[$value] = true;
            }
        }

        return $values;
    }
}
