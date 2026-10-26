<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\GaiaDev;

use GaiaDev\Extractor\AclExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the ACL rules extractor (agaia-4129).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AclExtractorTest extends TestCase
{
    public function testExtractsScopesModesScopedResourcesAndActionMap()
    {
        $acl = (new AclExtractor(__DIR__ . '/fixtures/repo'))->extract();

        $this->assertSame('core/libs/security/Acl.php', $acl['file']);
        $this->assertSame(['none' => 0, 'all' => 1, 'members' => 2], $acl['scopes']);
        $this->assertSame(['permissive', 'restrictive'], $acl['resolutionModes']);
        $this->assertSame(['widget.get'], $acl['scopedResources']);
        $this->assertSame(['isScopedResource'], $acl['methods']);
        $this->assertSame(['get' => 'get', 'post' => 'create'], $acl['actions']);
    }

    public function testReadsTheRepositoryAcl()
    {
        $acl = (new AclExtractor(dirname(__DIR__, 3)))->extract();

        $this->assertSame(['none' => 0, 'all' => 1, 'members' => 2], $acl['scopes']);
        $this->assertContains('project.get', $acl['scopedResources']);
        $this->assertSame('update', $acl['actions']['patch']);
        $this->assertSame('create', $acl['actions']['post']);
        $this->assertContains('authorizeAction', $acl['methods']);
    }
}
