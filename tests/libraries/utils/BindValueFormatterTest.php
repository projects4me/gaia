<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\BindValueFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for formatting bind values in the DB log.
 */
class BindValueFormatterTest extends TestCase
{
    public function testJoinFormatsArraysAsJsonWithoutWarnings()
    {
        $this->assertSame('a, ["x","y"], 3', BindValueFormatter::join(['a', ['x', 'y'], 3]));
    }

    public function testScalarsAndNull()
    {
        $this->assertSame('NULL', BindValueFormatter::format(null));
        $this->assertSame('true', BindValueFormatter::format(true));
        $this->assertSame('false', BindValueFormatter::format(false));
        $this->assertSame('1.5', BindValueFormatter::format(1.5));
    }

    public function testAssociativeArrayBecomesObject()
    {
        $this->assertSame('{"a":1}', BindValueFormatter::format(['a' => 1]));
    }
}
