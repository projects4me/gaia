<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Libraries\Security\Acl;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for project.get record-scope helpers on Acl.
 *
 * Requires Phalcon (same as RolePermissionSeederTest / AclLockoutGuardTest).
 */
class AclRecordScopeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
    }

    public function testNormalizeScopeValueRecognizesNoneAllMembersOnly()
    {
        $acl = $this->newAcl();

        $this->assertSame(Acl::SCOPE_NONE, $acl->normalizeScopeValue(null));
        $this->assertSame(Acl::SCOPE_NONE, $acl->normalizeScopeValue(''));
        $this->assertSame(Acl::SCOPE_NONE, $acl->normalizeScopeValue(0));
        $this->assertSame(Acl::SCOPE_NONE, $acl->normalizeScopeValue('0'));
        $this->assertSame(Acl::SCOPE_ALL, $acl->normalizeScopeValue(1));
        $this->assertSame(Acl::SCOPE_ALL, $acl->normalizeScopeValue('1'));
        $this->assertSame(Acl::SCOPE_MEMBERS, $acl->normalizeScopeValue(2));
        $this->assertSame(Acl::SCOPE_MEMBERS, $acl->normalizeScopeValue('2'));
        $this->assertSame(Acl::SCOPE_NONE, $acl->normalizeScopeValue(3));
        $this->assertSame(Acl::SCOPE_NONE, $acl->normalizeScopeValue('99'));
    }

    public function testIsScopedResourceIncludesProjectGet()
    {
        $acl = $this->newAcl();
        $this->assertTrue($acl->isScopedResource('project.get'));
        $this->assertFalse($acl->isScopedResource('project.create'));
        $this->assertFalse($acl->isScopedResource('issue.get'));
    }

    /**
     * @return Acl
     */
    private function newAcl()
    {
        global $settings;
        if (!isset($settings['system']['acl']['scopedResources'])) {
            $settings['system']['acl']['scopedResources'] = ['project.get'];
        }

        return new Acl(\Phalcon\Di::getDefault(), Acl::RESOLUTION_PERMISSIVE);
    }
}
