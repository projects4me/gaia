<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Libraries\Security\Acl;
use Gaia\Libraries\Security\TokenScope;
use PHPUnit\Framework\TestCase;

/**
 * Acl holds a scoped token to its scope on top of the actor's ACL (D-064):
 * the requested action, related reads (D-020) and included relationships
 * (D-021) are narrowed; an unscoped token keeps the actor's ACL.
 *
 * The actor here is allowed everything, so any denial comes from the scope.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AclTokenScopeTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
    }

    public function testScopedTokenMayPerformCoveredAction()
    {
        $acl = $this->newAcl('issue.get');

        $this->assertTrue($acl->checkModelAccess('Issue', 'get'));
        $this->assertTrue($acl->checkAccess('issue.get'));
    }

    public function testScopedTokenIsDeniedUncoveredActionTheActorHolds()
    {
        $acl = $this->newAcl('issue.get');

        $this->assertFalse($acl->isModelActionAllowed('Issue', 'update'));
        $this->expectException(\Gaia\Exception\Access::class);
        $acl->checkModelAccess('Issue', 'update');
    }

    public function testScopedTokenIsDeniedUncoveredResourceByName()
    {
        $this->expectException(\Gaia\Exception\Access::class);
        $this->newAcl('issue.*')->checkAccess('comment.create');
    }

    public function testScopedTokenIsDeniedExcludedResourceEvenUnderFullWildcard()
    {
        $acl = $this->newAcl('*.*');

        $this->assertTrue($acl->isModelActionAllowed('Issue', 'delete'));
        $this->assertFalse($acl->isModelActionAllowed('Role', 'get'));
        $this->assertFalse($acl->isModelActionAllowed('Agent', 'update'));
    }

    public function testRelatedReadNeedsTheRelatedModelInScope()
    {
        $acl = $this->newAcl('issue.get');

        $this->expectException(\Gaia\Exception\Access::class);
        $acl->authorizeRelated('Issue', 'comments', 'get');
    }

    public function testRelatedReadCoveredByScopeIsAllowed()
    {
        $acl = $this->newAcl('issue.get comment.get');

        $acl->authorizeRelated('Issue', 'comments', 'get');
        $this->addToAssertionCount(1);
    }

    public function testIncludedRelationshipsOutsideScopeAreOmitted()
    {
        $acl = $this->newAcl('issue.get project.get');

        $this->assertSame(
            ['project'],
            array_values($acl->filterAuthorizedRelationships('Issue', ['project', 'comments'], 'get'))
        );
    }

    public function testUnscopedTokenKeepsTheActorsAcl()
    {
        foreach ([null, 'application'] as $scope) {
            $acl = $this->newAcl($scope);

            $this->assertTrue($acl->isModelActionAllowed('Issue', 'update'));
            $this->assertTrue($acl->isModelActionAllowed('Role', 'update'));
            $this->assertSame(
                ['project', 'comments'],
                array_values($acl->filterAuthorizedRelationships('Issue', ['project', 'comments'], 'get'))
            );
        }
    }

    /**
     * An Acl whose actor holds every permission, with the given token scope.
     *
     * @param  string|null $scope
     * @return Acl
     */
    private function newAcl($scope)
    {
        $acl = new class(\Phalcon\Di::getDefault(), Acl::RESOLUTION_PERMISSIVE) extends Acl {
            protected function isResourceAllowed($resourceName)
            {
                return true;
            }

            protected function getAuthorizationGroups($modelName)
            {
                return [];
            }

            protected function getRelatedModelName($modelAlias, $relName)
            {
                return ['project' => 'Project', 'comments' => 'Comment'][$relName];
            }

            protected function denyAccess($detail)
            {
                throw new \Gaia\Exception\Access('Access Denied');
            }
        };

        return $acl->setTokenScope(new TokenScope($scope));
    }
}
