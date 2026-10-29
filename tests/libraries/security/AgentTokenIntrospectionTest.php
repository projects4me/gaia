<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Agents\AgentCredentials;
use Gaia\Libraries\Agents\AgentLifecycle;
use Gaia\Libraries\Agents\AgentTokenIntrospection;
use Gaia\Libraries\Oauth\Storage\Pdo;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Agenttool;
use Gaia\MVC\Models\Tool;
use Gaia\MVC\Models\Toolserver;
use Gaia\MVC\Models\User;

/**
 * Tests which tokens introspect as active agent tokens, and the agent and
 * tool grants an active one carries.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentTokenIntrospectionTest extends TestCase
{
    /**
     * Human user that creates the agents and the catalog rows.
     */
    public const ISSUER_ID = 'test-user-agent-introspect';

    private const ISSUER_EMAIL = 'agent-introspect-issuer@gaia.test';

    private const SERVER_ID = 'unit-introspect-server';

    private const OTHER_SERVER_ID = 'unit-introspect-server-2';

    private const TOOL_ID = 'unit-introspect-tool';

    private const TOKEN_PREFIX = 'unit-introspect-';

    public static function setUpBeforeClass(): void
    {
        AgentTestFixtures::ensureHumanUser(self::ISSUER_ID, self::ISSUER_EMAIL);
    }

    public static function tearDownAfterClass(): void
    {
        self::purge();
        AgentTestFixtures::deleteUser(self::ISSUER_ID);
    }

    protected function setUp(): void
    {
        global $currentUser;

        $currentUser = User::findFirstById(self::ISSUER_ID);
        self::purge();
    }

    public function testAnAgentTokenIsActiveWithItsAgentAndGrants(): void
    {
        $agent = $this->createAgent('Unit Introspect', 'unit-agent-is@gaia.test');
        $token = $this->issueToken($agent, 'unit-agent-is@gaia.test');
        $this->createServer(self::SERVER_ID, 'p4m-tracker');
        $this->createServer(self::OTHER_SERVER_ID, 'github');
        $this->createTool(self::TOOL_ID, 'create_issue', self::OTHER_SERVER_ID);
        $this->grant($agent, self::SERVER_ID, null, 'allow');
        $this->grant($agent, null, self::TOOL_ID, 'ask');

        $result = $this->introspection()->introspect($token);

        $this->assertTrue($result['active']);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertSame($agent->userId, $result['sub']);
        $this->assertSame('unit-agent-is@gaia.test', $result['username']);
        $this->assertGreaterThan(time(), $result['exp']);
        $this->assertSame($agent->id, $result['agent']['id']);
        $this->assertSame('Unit Introspect', $result['agent']['name']);

        $grants = $this->grantsByRule($result['toolGrants']);
        $this->assertCount(2, $grants);
        $this->assertSame(['id', 'name', 'kind', 'location', 'transport'], array_keys($grants['allow']['server']));
        $this->assertSame('p4m-tracker', $grants['allow']['server']['name']);
        $this->assertNull($grants['allow']['tool']);
        $this->assertSame('github', $grants['ask']['server']['name']);
        $this->assertSame(['id' => self::TOOL_ID, 'name' => 'create_issue'], $grants['ask']['tool']);
    }

    public function testNeverReturnsServerEndpointsOrSecrets(): void
    {
        $agent = $this->createAgent('Unit Introspect Secret', 'unit-agent-is-sec@gaia.test');
        $token = $this->issueToken($agent, 'unit-agent-is-sec@gaia.test');
        $this->createServer(self::SERVER_ID, 'p4m-tracker');
        $this->grant($agent, self::SERVER_ID, null, 'allow');

        $json = json_encode($this->introspection()->introspect($token));

        $this->assertStringNotContainsString('unit-secret-endpoint', $json);
        $this->assertStringNotContainsString('unit-secret-command', $json);
        $this->assertStringNotContainsString('unit-secret-spec', $json);
        $this->assertStringNotContainsString(AgentCredentials::SECRET_PREFIX, $json);
    }

    public function testLeavesOutGrantsOfDeletedToolsAndServers(): void
    {
        $agent = $this->createAgent('Unit Introspect Gone', 'unit-agent-is-gone@gaia.test');
        $token = $this->issueToken($agent, 'unit-agent-is-gone@gaia.test');
        $this->createServer(self::SERVER_ID, 'p4m-tracker');
        $this->createServer(self::OTHER_SERVER_ID, 'github');
        $this->createTool(self::TOOL_ID, 'create_issue', self::OTHER_SERVER_ID);
        $this->grant($agent, self::SERVER_ID, null, 'allow');
        $this->grant($agent, null, self::TOOL_ID, 'ask');

        Toolserver::findFirstById(self::OTHER_SERVER_ID)->delete();

        $grants = $this->introspection()->introspect($token)['toolGrants'];
        $this->assertCount(1, $grants);
        $this->assertSame('allow', $grants[0]['permissionRule']);
    }

    public function testAnExpiredTokenIsInactive(): void
    {
        $agent = $this->createAgent('Unit Introspect Expired', 'unit-agent-is-exp@gaia.test');
        $token = $this->issueToken($agent, 'unit-agent-is-exp@gaia.test', time() - 60);

        $this->assertSame(['active' => false], $this->introspection()->introspect($token));
    }

    public function testAnUnknownOrEmptyTokenIsInactive(): void
    {
        $this->assertSame(['active' => false], $this->introspection()->introspect(self::TOKEN_PREFIX . 'unknown'));
        $this->assertSame(['active' => false], $this->introspection()->introspect(''));
        $this->assertSame(['active' => false], $this->introspection()->introspect(null));
    }

    public function testAPersonsTokenIsInactive(): void
    {
        $token = self::TOKEN_PREFIX . 'human';
        $this->storage()->setAccessToken($token, 'unit-introspect-app', self::ISSUER_EMAIL, time() + 3600);

        $this->assertSame(['active' => false], $this->introspection()->introspect($token));
    }

    public function testATokenOfADisabledAgentIsInactive(): void
    {
        $agent = $this->createAgent('Unit Introspect Off', 'unit-agent-is-off@gaia.test');
        $token = $this->issueToken($agent, 'unit-agent-is-off@gaia.test');
        $user = User::findFirstById($agent->userId);
        $user->accountStatus = 'Inactive';
        $this->assertTrue($user->save());

        $this->assertSame(['active' => false], $this->introspection()->introspect($token));
    }

    public function testATokenOfAnotherClientIsInactive(): void
    {
        $agent = $this->createAgent('Unit Introspect Client', 'unit-agent-is-cli@gaia.test');
        (new AgentCredentials())->issue($agent->id);
        $token = self::TOKEN_PREFIX . 'other-client';
        $this->storage()->setAccessToken(
            $token,
            'unit-introspect-app',
            'unit-agent-is-cli@gaia.test',
            time() + 3600
        );

        $this->assertSame(['active' => false], $this->introspection()->introspect($token));
    }

    /**
     * @return AgentTokenIntrospection
     */
    private function introspection()
    {
        return new AgentTokenIntrospection($this->storage());
    }

    /**
     * @param  string $name
     * @param  string $email
     * @return Agent
     */
    private function createAgent($name, $email)
    {
        return (new AgentLifecycle())->create(['name' => $name, 'email' => $email]);
    }

    /**
     * An access token as the client_credentials grant stores it.
     *
     * @param  Agent    $agent
     * @param  string   $email
     * @param  int|null $expires
     * @return string
     */
    private function issueToken(Agent $agent, $email, $expires = null)
    {
        $clientId = (new AgentCredentials())->issue($agent->id)['credential']->clientId;
        $token = self::TOKEN_PREFIX . bin2hex(random_bytes(8));
        $this->storage()->setAccessToken($token, $clientId, $email, $expires ?: time() + 3600);

        return $token;
    }

    private function createServer($id, $name)
    {
        $server = new Toolserver();
        $server->id = $id;
        $server->name = $name;
        $server->kind = 'mcp';
        $server->location = 'external';
        $server->transport = 'http';
        $server->endpoint = 'https://unit-secret-endpoint.test/mcp';
        $server->command = 'unit-secret-command';
        $server->spec = '{"token":"unit-secret-spec"}';
        $this->assertTrue($server->save(), implode(' ', $server->getMessages()));
    }

    private function createTool($id, $name, $serverId)
    {
        $tool = new Tool();
        $tool->id = $id;
        $tool->name = $name;
        $tool->toolServerId = $serverId;
        $this->assertTrue($tool->save(), implode(' ', $tool->getMessages()));
    }

    private function grant(Agent $agent, $serverId, $toolId, $rule)
    {
        $grant = new Agenttool();
        $grant->id = 'unit-introspect-grant-' . $rule;
        $grant->agentId = $agent->id;
        $grant->toolServerId = $serverId;
        $grant->toolId = $toolId;
        $grant->permissionRule = $rule;
        $this->assertTrue($grant->save(), implode(' ', $grant->getMessages()));
    }

    /**
     * @param  array $grants
     * @return array Grants keyed by permission rule
     */
    private function grantsByRule(array $grants)
    {
        $byRule = [];
        foreach ($grants as $grant) {
            $byRule[$grant['permissionRule']] = $grant;
        }

        return $byRule;
    }

    /**
     * @return Pdo
     */
    private function storage()
    {
        return new Pdo(\Phalcon\Di::getDefault()->get('db')->getInternalHandler(), ['user_table' => 'users']);
    }

    private static function purge()
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute("DELETE FROM oauth_access_tokens WHERE access_token LIKE 'unit-introspect-%'");
        $db->execute("DELETE FROM agent_tools WHERE id LIKE 'unit-introspect-%'");
        $db->execute("DELETE FROM tools WHERE id LIKE 'unit-introspect-%'");
        $db->execute("DELETE FROM tool_servers WHERE id LIKE 'unit-introspect-%'");
        AgentTestFixtures::purge();
    }
}
