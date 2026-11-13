<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\ServerTiming;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Server-Timing collector.
 */
class ServerTimingTest extends TestCase
{
    public function testFormatsDurationsInMillisecondsWithCountsAndDescriptions()
    {
        $timing = new ServerTiming();
        $timing->set('boot', 0.0071);
        $timing->add('schema', 0.012);
        $timing->add('schema', 0.012);
        $timing->describe('schema', '4 tables');
        $timing->add('db', 0.0098);
        $timing->set('total', 0.0622);

        $this->assertSame(
            'boot;dur=7.1, schema;dur=24.0;desc="4 tables", db;dur=9.8;desc="1 queries", total;dur=62.2',
            $timing->format(['db' => 'queries'])
        );
    }

    public function testAccumulatesCountsPerMetric()
    {
        $timing = new ServerTiming();
        $timing->add('db', 0.001);
        $timing->add('db', 0.002);

        $this->assertSame(2, $timing->count('db'));
        $this->assertSame(0, $timing->count('other'));
        $this->assertSame('db;dur=3.0;desc="2 queries"', $timing->format(['db' => 'queries']));
    }

    public function testEscapesQuotesInDescriptions()
    {
        $timing = new ServerTiming();
        $timing->set('x', 0.001);
        $timing->describe('x', 'a"b');

        $this->assertSame('x;dur=1.0;desc="a\\"b"', $timing->format());
    }

    public function testEmptyWhenNothingRecorded()
    {
        $this->assertSame('', (new ServerTiming())->format());
    }

    public function testEnsureReportsAMetricWithZeroDurationWithoutOverwriting()
    {
        $timing = new ServerTiming();
        $timing->ensure('schema');
        $timing->add('db', 0.002);
        $timing->ensure('db');

        $this->assertSame('schema;dur=0.0, db;dur=2.0', $timing->format());
    }
}
