<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Tools;

use Gaia\Libraries\Tools\ToolCatalogRules;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Tool;
use Gaia\MVC\Models\Toolserver;
use PHPUnit\Framework\TestCase;

/**
 * Tests the tool catalog write rules: server kinds, locations and
 * transports, JSON fields, and grants naming exactly one existing target.
 * Row lookups are stubbed, so no database is needed.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ToolCatalogRulesTest extends TestCase
{
    public function testAcceptsAValidServer()
    {
        $this->rules()->assertServer([
            'kind' => 'mcp',
            'location' => 'external',
            'transport' => 'http',
            'endpoint' => 'https://mcp.test/mcp',
            'spec' => '{"openapi":"3.1.0"}',
        ]);
        $this->rules()->assertServer(['name' => 'Only a rename']);
        $this->rules()->assertServer(['transport' => null]);
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider invalidServers
     */
    public function testRejectsAnInvalidServer(array $attributes, $message)
    {
        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage($message);
        $this->rules()->assertServer($attributes);
    }

    public function invalidServers()
    {
        return [
            'kind' => [['kind' => 'grpc'], 'Kind must be one of: mcp, openapi, builtin.'],
            'empty kind' => [['kind' => ''], 'Kind must be one of: mcp, openapi, builtin.'],
            'location' => [['location' => 'cloud'], 'Location must be one of: runtime, ares, external.'],
            'transport' => [['transport' => 'websocket'], 'Transport must be one of: stdio, http, sse.'],
            'spec' => [['spec' => 'not json'], 'Spec must be a JSON object.'],
            'spec list' => [['spec' => '[1,2]'], 'Spec must be a JSON object.'],
            'file endpoint' => [['endpoint' => 'file:///etc/passwd'], 'Endpoint must be an http or https URL.'],
            'gopher endpoint' => [['endpoint' => 'gopher://mcp.test/'], 'Endpoint must be an http or https URL.'],
            'bare endpoint' => [['endpoint' => 'mcp.test/mcp'], 'Endpoint must be an http or https URL.'],
        ];
    }

    public function testATool()
    {
        $this->rules()->assertTool(['name' => 'Read', 'inputSchema' => '{"type":"object"}']);
        $this->rules()->assertTool(['toolServerId' => 'server-1', 'name' => 'echo']);
        $this->addToAssertionCount(1);
    }

    public function testRejectsAToolSchemaThatIsNotAnObject()
    {
        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage('InputSchema must be a JSON object.');
        $this->rules()->assertTool(['inputSchema' => '"string"']);
    }

    public function testRejectsAToolOfAMissingServer()
    {
        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage('A tool can only belong to an existing tool server.');
        $this->rules()->assertTool(['toolServerId' => 'no-such-server']);
    }

    public function testAcceptsAServerGrantAndAToolGrant()
    {
        $this->assertSame('server-1', $this->rules()->assertGrant(['agentId' => 'agent-1', 'toolServerId' => 'server-1'])['toolServerId']);
        $this->assertSame('tool-1', $this->rules()->assertGrant(['agentId' => 'agent-1', 'toolId' => 'tool-1', 'permissionRule' => 'Bash(git status:*)'])['toolId']);
    }

    /**
     * @dataProvider invalidGrants
     */
    public function testRejectsAnInvalidGrant(array $attributes, $message)
    {
        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage($message);
        $this->rules()->assertGrant($attributes);
    }

    public function invalidGrants()
    {
        return [
            'no agent' => [['toolServerId' => 'server-1'], 'A tool grant needs an existing agent.'],
            'missing agent' => [['agentId' => 'no-such-agent', 'toolServerId' => 'server-1'], 'A tool grant needs an existing agent.'],
            'no target' => [['agentId' => 'agent-1'], 'A tool grant names either a tool server or a tool, not both.'],
            'both targets' => [['agentId' => 'agent-1', 'toolServerId' => 'server-1', 'toolId' => 'tool-1'], 'A tool grant names either a tool server or a tool, not both.'],
            'missing server' => [['agentId' => 'agent-1', 'toolServerId' => 'no-such-server'], 'A tool grant needs an existing tool server.'],
            'missing tool' => [['agentId' => 'agent-1', 'toolId' => 'no-such-tool'], 'A tool grant needs an existing tool.'],
        ];
    }

    public function testAnUpdateIsCheckedOverTheStoredGrant()
    {
        $rules = $this->rules(['grant-1' => ['agentId' => 'agent-1', 'toolServerId' => 'server-1', 'toolId' => null]]);

        $this->assertSame('Bash(git:*)', $rules->assertGrant(['permissionRule' => 'Bash(git:*)'], 'grant-1')['permissionRule']);

        $this->expectExceptionMessage('A tool grant names either a tool server or a tool, not both.');
        $rules->assertGrant(['toolId' => 'tool-1'], 'grant-1');
    }

    /**
     * Rules over a fixed set of rows: agent-1, server-1, tool-1.
     *
     * @param  array $grants Stored grants by id
     * @return ToolCatalogRules
     */
    private function rules(array $grants = [])
    {
        return new class ($grants) extends ToolCatalogRules {
            private $grants;

            public function __construct(array $grants)
            {
                $this->grants = $grants;
            }

            protected function storedGrant($grantId)
            {
                return isset($this->grants[$grantId]) ? $this->grants[$grantId] : [];
            }

            protected function exists($modelClass, $id)
            {
                $rows = [Agent::class => 'agent-1', Toolserver::class => 'server-1', Tool::class => 'tool-1'];
                return isset($rows[$modelClass]) && $rows[$modelClass] === $id;
            }
        };
    }
}
