<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Graph\RouteMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for matching api-tester case paths to routes (agaia-4128).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RouteMatcherTest extends TestCase
{
    /**
     * @return RouteMatcher
     */
    private function matcher(): RouteMatcher
    {
        $matcher = new RouteMatcher();
        $matcher->add('GET /api/v1/issue/{id}', 'GET', '/api/v1/issue/{id}');
        $matcher->add('GET /api/v1/issue/{id}/{relation}', 'GET', '/api/v1/issue/{id}/{relation}');
        $matcher->add('GET /api/v1/issue', 'GET', '/api/v1/issue');
        $matcher->add('ANY /translations', 'ANY', '/translations');
        $matcher->add('ANY /translations/{locale}', 'ANY', '/translations/{locale}');
        $matcher->add('system:not_found', null, null);

        return $matcher;
    }

    public function testCapturedSegmentsMatchPlaceholdersAndLiterals()
    {
        $matcher = $this->matcher();

        $this->assertSame('GET /api/v1/issue/{id}', $matcher->match('GET', '/api/v1/issue/{issueId}'));
        $this->assertSame('GET /api/v1/issue/{id}', $matcher->match('GET', '/api/v1/issue/abc-123'));
        $this->assertSame('GET /api/v1/issue/{id}/{relation}', $matcher->match('GET', '/api/v1/issue/{issueId}/comments'));
    }

    public function testQueryStringIsIgnored()
    {
        $this->assertSame('GET /api/v1/issue', $this->matcher()->match('GET', '/api/v1/issue?limit=5'));
    }

    public function testMethodMustMatchUnlessTheRouteAcceptsAny()
    {
        $matcher = $this->matcher();

        $this->assertNull($matcher->match('DELETE', '/api/v1/issue/{issueId}'));
        $this->assertSame('ANY /translations/{locale}', $matcher->match('POST', '/translations/en-us'));
    }

    public function testLiteralRouteWinsOverACapturedOne()
    {
        $matcher = $this->matcher();
        $matcher->add('GET /api/v1/issue/export', 'GET', '/api/v1/issue/export');

        $this->assertSame('GET /api/v1/issue/export', $matcher->match('GET', '/api/v1/issue/export'));
    }

    public function testUnknownPathsDoNotMatch()
    {
        $this->assertNull($this->matcher()->match('POST', '/api/v1/token'));
    }
}
