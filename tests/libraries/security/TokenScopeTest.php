<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Libraries\Security\TokenScope;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TokenScope: matching, wildcards and the hard exclusions (D-064).
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Tests\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TokenScopeTest extends TestCase
{
    public function testTokenWithoutScopeIsUnscopedAndCoversEverything()
    {
        foreach ([null, '', '   '] as $scope) {
            $tokenScope = new TokenScope($scope);
            $this->assertFalse($tokenScope->isScoped());
            $this->assertTrue($tokenScope->covers('issue.delete'));
            $this->assertTrue($tokenScope->covers('role.update'));
        }
    }

    public function testLegacyDefaultScopeDoesNotNarrow()
    {
        $tokenScope = new TokenScope('application');

        $this->assertFalse($tokenScope->isScoped());
        $this->assertTrue($tokenScope->covers('permission.update'));
    }

    public function testExactPermissionCoversOnlyThatAction()
    {
        $tokenScope = new TokenScope('issue.get comment.create');

        $this->assertTrue($tokenScope->isScoped());
        $this->assertTrue($tokenScope->covers('issue.get'));
        $this->assertTrue($tokenScope->covers('comment.create'));
        $this->assertFalse($tokenScope->covers('issue.update'));
        $this->assertFalse($tokenScope->covers('comment.get'));
        $this->assertFalse($tokenScope->covers('project.get'));
    }

    public function testDefaultScopeWordIsIgnoredBesidePermissions()
    {
        $tokenScope = new TokenScope('application issue.get');

        $this->assertTrue($tokenScope->isScoped());
        $this->assertTrue($tokenScope->covers('issue.get'));
        $this->assertFalse($tokenScope->covers('issue.update'));
    }

    public function testModuleWildcardCoversEveryActionOfTheModule()
    {
        $tokenScope = new TokenScope('issue.*');

        $this->assertTrue($tokenScope->covers('issue.get'));
        $this->assertTrue($tokenScope->covers('issue.delete'));
        $this->assertFalse($tokenScope->covers('issuetype.get'));
        $this->assertFalse($tokenScope->covers('comment.get'));
    }

    public function testActionWildcardCoversTheActionOnEveryModule()
    {
        $tokenScope = new TokenScope('*.get');

        $this->assertTrue($tokenScope->covers('issue.get'));
        $this->assertTrue($tokenScope->covers('project.get'));
        $this->assertFalse($tokenScope->covers('issue.create'));
    }

    public function testMatchingIsCaseInsensitiveAndLiteral()
    {
        $tokenScope = new TokenScope('Issue.GET');

        $this->assertTrue($tokenScope->covers('issue.get'));
        $this->assertTrue($tokenScope->covers('ISSUE.get'));
        $this->assertFalse((new TokenScope('issue.ge'))->covers('issue.get'));
        $this->assertFalse((new TokenScope('issue.g.t'))->covers('issue.get'));
    }

    /**
     * @dataProvider excludedResources
     */
    public function testHardExclusionsHoldWhateverTheScope($resourceName)
    {
        $tokenScope = new TokenScope('*.* ' . $resourceName);

        $this->assertFalse($tokenScope->covers($resourceName));
    }

    public function excludedResources()
    {
        return [
            ['agentcredential.create'],
            ['agentcredential.get'],
            ['serviceclient.get'],
            ['enrollment.create'],
            ['enrollmenttoken.get'],
            ['role.get'],
            ['role.update'],
            ['userrole.create'],
            ['permission.update'],
            ['agent.create'],
            ['agent.update'],
            ['agent.delete'],
        ];
    }

    public function testAgentReadsAreNotExcluded()
    {
        $this->assertTrue((new TokenScope('agent.get'))->covers('agent.get'));
    }

    public function testNamesMatchesOnlyTheExactWord()
    {
        $this->assertTrue((new TokenScope('issue.get Serviceclient.Rotate'))->names('serviceclient.rotate'));

        foreach (['serviceclient.*', '*.*', 'serviceclient.rotate.x', 'application', ''] as $scope) {
            $this->assertFalse((new TokenScope($scope))->names('serviceclient.rotate'), $scope);
        }
    }
}
