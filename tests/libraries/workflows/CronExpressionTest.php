<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workflows;

use Gaia\Libraries\Workflows\CronExpression;
use PHPUnit\Framework\TestCase;

/**
 * Tests parsing five-field cron expressions and matching minutes against them.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CronExpressionTest extends TestCase
{
    /**
     * @dataProvider minutes
     */
    public function testMatchesAMinute($expression, $at, $expected)
    {
        $minute = new \DateTimeImmutable($at, new \DateTimeZone('UTC'));

        $this->assertSame($expected, (new CronExpression($expression))->matches($minute));
    }

    public function minutes()
    {
        // 2026-11-02 is a Monday
        return [
            'every minute' => ['* * * * *', '2026-11-02 13:37', true],
            'exact time' => ['30 9 * * *', '2026-11-02 09:30', true],
            'other time' => ['30 9 * * *', '2026-11-02 09:31', false],
            'step' => ['*/15 * * * *', '2026-11-02 10:45', true],
            'step misses' => ['*/15 * * * *', '2026-11-02 10:44', false],
            'start and step' => ['5/20 * * * *', '2026-11-02 10:45', true],
            'range' => ['0 9-17 * * *', '2026-11-02 17:00', true],
            'range misses' => ['0 9-17 * * *', '2026-11-02 18:00', false],
            'list' => ['0 9 * * 1,3,5', '2026-11-02 09:00', true],
            'weekdays' => ['0 9 * * 1-5', '2026-11-01 09:00', false],
            'sunday as 7' => ['0 9 * * 7', '2026-11-01 09:00', true],
            'sunday as 0' => ['0 9 * * 0', '2026-11-01 09:00', true],
            'month' => ['0 0 1 11 *', '2026-11-01 00:00', true],
            'month misses' => ['0 0 1 12 *', '2026-11-01 00:00', false],
            'day of month or week: by month' => ['0 0 15 * 5', '2026-11-15 00:00', true],
            'day of month or week: by week' => ['0 0 15 * 1', '2026-11-02 00:00', true],
            'day of month or week: neither' => ['0 0 15 * 5', '2026-11-02 00:00', false],
            'day of month and any week' => ['0 0 2 * *', '2026-11-03 00:00', false],
        ];
    }

    /**
     * @dataProvider invalid
     */
    public function testExplainsAnInvalidExpression($expression, $message)
    {
        $this->assertSame($message, CronExpression::error($expression));
    }

    public function invalid()
    {
        return [
            'too few fields' => ['* * * *', 'must have five fields: minute, hour, day of month, month and day of week.'],
            'not a string' => [['*'], 'must have five fields: minute, hour, day of month, month and day of week.'],
            'word' => ['* * * * MON', "has an invalid day of week field 'MON'."],
            'out of range' => ['60 * * * *', "has a minute out of range 0-59 in '60'."],
            'reversed range' => ['* 5-2 * * *', "has a hour out of range 0-23 in '5-2'."],
            'zero step' => ['*/0 * * * *', "has a minute out of range 0-59 in '*/0'."],
            'zero day' => ['* * 0 * *', "has a day of month out of range 1-31 in '0'."],
        ];
    }

    public function testAcceptsAValidExpression()
    {
        $this->assertNull(CronExpression::error('0 9 * * 1-5'));
        $this->assertNull(CronExpression::error(' */5  0-6/2 1,15 * * '));
    }
}
