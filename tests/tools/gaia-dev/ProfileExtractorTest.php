<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\FixtureExtractor;
use GaiaDev\Extractor\ProfileExtractor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for the harness profile extractor (agaia-4129).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProfileExtractorTest extends TestCase
{
    public function testExtractsUserRoleAndPermissionsPerProfile()
    {
        $profiles = array_column((new ProfileExtractor(__DIR__ . '/fixtures/repo'))->extract(), null, 'name');

        $this->assertSame(['default', 'limited'], array_keys($profiles));
        $this->assertSame(['id' => 'fixture-user-1', 'email' => 'fixture@example.com'], $profiles['default']['user']);
        $this->assertSame('Admin', $profiles['default']['role']['name']);
        $this->assertSame(
            ['tag.create' => 1, 'tag.get' => 1, 'widget.create' => 1, 'widget.get' => 1],
            $profiles['default']['permissions']
        );
        $this->assertSame('Limited', $profiles['limited']['role']['name']);
        $this->assertSame(
            ['widget.create' => 0, 'widget.get' => 2, 'widget.secret.get' => 0],
            $profiles['limited']['permissions']
        );
    }

    public function testCredentialsAreNeverRead()
    {
        $json = json_encode((new ProfileExtractor(__DIR__ . '/fixtures/repo'))->extract());

        $this->assertStringNotContainsString('fixture-password', $json);
        $this->assertStringNotContainsString('fixturehash', $json);
    }

    public function testEveryRepositoryFixtureProfileIsResolved()
    {
        $root = dirname(__DIR__, 3);
        $profiles = array_column((new ProfileExtractor($root))->extract(), null, 'name');

        $expected = array_diff((new FixtureExtractor($root))->extract()['profiles'], ['anonymous']);
        $this->assertSame([], array_values(array_diff($expected, array_keys($profiles))));
        $this->assertSame(2, $profiles['aclMembers']['permissions']['project.get']);
        $this->assertSame(0, $profiles['aclFields']['permissions']['issue.subject.get']);
        $this->assertSame(1, $profiles['default']['permissions']['issue.get']);
    }

    public function testFailsWhenTheScriptIsMissing()
    {
        $this->expectException(RuntimeException::class);

        (new ProfileExtractor(__DIR__ . '/fixtures/missing'))->extract();
    }
}
