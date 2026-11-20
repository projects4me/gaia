<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Workspaces;

use Gaia\Libraries\Workspaces\RepositoryUrl;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RepositoryUrl: https and ssh git URLs pass, credentials and
 * other schemes do not.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RepositoryUrlTest extends TestCase
{
    /**
     * @return array[]
     */
    public static function acceptedUrls()
    {
        return [
            'https' => ['https://github.com/projects4me/gaia.git'],
            'https with port' => ['https://git.example.com:8443/team/repo'],
            'ssh url' => ['ssh://git@github.com/projects4me/gaia.git'],
            'scp-like' => ['git@github.com:projects4me/gaia.git'],
        ];
    }

    /**
     * @dataProvider acceptedUrls
     * @param string $url
     */
    public function testAcceptsAGitUrl($url)
    {
        $this->assertNull((new RepositoryUrl())->error($url));
    }

    /**
     * @return array[]
     */
    public static function credentialUrls()
    {
        return [
            'https token' => ['https://user:token@github.com/projects4me/gaia.git'],
            'https user only' => ['https://token@github.com/projects4me/gaia.git'],
            'ssh password' => ['ssh://git:secret@github.com/projects4me/gaia.git'],
        ];
    }

    /**
     * @dataProvider credentialUrls
     * @param string $url
     */
    public function testRejectsCredentials($url)
    {
        $this->assertSame(RepositoryUrl::CREDENTIALS_ERROR, (new RepositoryUrl())->error($url));
    }

    /**
     * @return array[]
     */
    public static function invalidUrls()
    {
        return [
            'empty' => [''],
            'null' => [null],
            'plain text' => ['not a url'],
            'http' => ['http://github.com/projects4me/gaia.git'],
            'file' => ['file:///srv/git/gaia.git'],
            'no host' => ['https:///gaia.git'],
            'space' => ['https://github.com/a b.git'],
        ];
    }

    /**
     * @dataProvider invalidUrls
     * @param mixed $url
     */
    public function testRejectsAnythingElse($url)
    {
        $this->assertSame(RepositoryUrl::INVALID_ERROR, (new RepositoryUrl())->error($url));
    }
}
