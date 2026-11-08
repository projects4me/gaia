<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Authorization;

use Gaia\Libraries\Agents\AgentDelegationScopes;
use PHPUnit\Framework\TestCase;

/**
 * Tests reading an agent's delegation scopes and the scope a delegated token
 * gets from them (D-067).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentDelegationScopesTest extends TestCase
{
    public function testEmptyStoredScopesAreAnEmptyList(): void
    {
        $this->assertSame([], AgentDelegationScopes::decode(null));
        $this->assertSame([], AgentDelegationScopes::decode(''));
        $this->assertSame([], AgentDelegationScopes::decode('[]'));
    }

    public function testStoredScopesMustBePermissionPatterns(): void
    {
        $this->assertSame(['issue.*', '*.get'], AgentDelegationScopes::decode('["issue.*","*.get"]'));
        $this->assertNull(AgentDelegationScopes::decode('["application"]'));
        $this->assertNull(AgentDelegationScopes::decode('"issue.get"'));
        $this->assertNull(AgentDelegationScopes::decode('["issue get"]'));
    }

    public function testNoRequestedScopeGrantsAllOfThem(): void
    {
        $this->assertSame('issue.* comment.get', AgentDelegationScopes::grant(['issue.*', 'comment.get'], null));
        $this->assertSame('issue.* comment.get', AgentDelegationScopes::grant(['issue.*', 'comment.get'], '  '));
    }

    public function testCoveredRequestsAreGranted(): void
    {
        $this->assertSame('issue.get', AgentDelegationScopes::grant(['issue.*'], 'issue.get'));
        $this->assertSame('issue.*', AgentDelegationScopes::grant(['*.*'], 'issue.*'));
        $this->assertSame('issue.g*', AgentDelegationScopes::grant(['issue.*'], 'issue.g*'));
        $this->assertSame('issue.get', AgentDelegationScopes::grant(['issue.get'], 'Issue.Get'));
        $this->assertSame('issue.get', AgentDelegationScopes::grant(['issue.get'], 'issue.get issue.get'));
    }

    public function testRequestsWiderThanTheAgentsScopesAreRefused(): void
    {
        $this->assertNull(AgentDelegationScopes::grant(['issue.*'], '*.get'));
        $this->assertNull(AgentDelegationScopes::grant(['issue.*'], 'iss*.get'));
        $this->assertNull(AgentDelegationScopes::grant(['issue.get'], 'issue.*'));
        $this->assertNull(AgentDelegationScopes::grant(['issue.get'], 'issue.get project.get'));
        $this->assertNull(AgentDelegationScopes::grant(['*.*'], 'application'));
    }
}
