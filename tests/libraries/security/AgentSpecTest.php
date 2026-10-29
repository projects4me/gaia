<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Agents\AgentLifecycle;
use Gaia\Libraries\Agents\AgentSpec;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\Agentskill;
use Gaia\MVC\Models\Agenttool;
use Gaia\MVC\Models\Skill;
use Gaia\MVC\Models\Tool;
use Gaia\MVC\Models\Toolserver;
use Gaia\MVC\Models\User;

/**
 * Tests the agent spec document: its content, and that it conforms to
 * docs/schemas/agent-spec.schema.json.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentSpecTest extends TestCase
{
    /**
     * Human user that creates the agents and the catalog rows.
     */
    public const ISSUER_ID = 'test-user-agent-spec';

    private const ISSUER_EMAIL = 'agent-spec-issuer@gaia.test';

    private const SERVER_ID = 'unit-spec-server';

    private const OTHER_SERVER_ID = 'unit-spec-server-2';

    private const TOOL_ID = 'unit-spec-tool';

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

    public function testTheSpecCarriesTheAgentConfigGrantsAndSkills(): void
    {
        $agent = $this->createAgent('unit-agent-spec@gaia.test', [
            'purpose' => 'Triage issues',
            'sponsorId' => self::ISSUER_ID,
            'rolePrompt' => 'You triage issues.',
            'modelConfig' => '{"model":"claude-sonnet-5-5","effort":"medium"}',
            'maxTurns' => 40,
            'maxRunsPerIssue' => 3,
            'permissionMode' => 'acceptEdits',
        ]);
        $this->createServer(self::SERVER_ID, 'p4m-tracker');
        $this->createServer(self::OTHER_SERVER_ID, 'github');
        $this->createTool(self::TOOL_ID, 'create_issue', self::OTHER_SERVER_ID);
        $this->grantTool($agent, 'server', self::SERVER_ID, null, 'allow');
        $this->grantTool($agent, 'tool', null, self::TOOL_ID, 'ask');
        $this->grantSkill($agent, 'triage', '# Triage', '2.0');
        $this->grantSkill($agent, 'review', '# Review', null);

        $spec = (new AgentSpec())->forAgent($agent->id);

        $this->assertConformsToSchema($spec);
        $this->assertSame(AgentSpec::SPEC_VERSION, $spec['specVersion']);
        $this->assertSame([
            'id' => $agent->id,
            'userId' => $agent->userId,
            'name' => 'Unit Spec Agent',
            'purpose' => 'Triage issues',
            'sponsorId' => self::ISSUER_ID,
        ], $spec['agent']);
        $this->assertSame('You triage issues.', $spec['config']['rolePrompt']);
        $this->assertSame(
            '{"model":"claude-sonnet-5-5","effort":"medium"}',
            json_encode($spec['config']['modelConfig'])
        );
        $this->assertSame(40, $spec['config']['maxTurns']);
        $this->assertSame(3, $spec['config']['maxRunsPerIssue']);
        $this->assertSame('acceptEdits', $spec['config']['permissionMode']);

        $this->assertCount(2, $spec['toolGrants']);
        $rules = array_column($spec['toolGrants'], 'permissionRule');
        sort($rules);
        $this->assertSame(['allow', 'ask'], $rules);

        $skills = array_column($spec['skills'], null, 'name');
        ksort($skills);
        $this->assertSame(['unit-spec-review', 'unit-spec-triage'], array_keys($skills));
        $this->assertSame('# Triage', $skills['unit-spec-triage']['content']);
        $this->assertSame('2.0', $skills['unit-spec-triage']['version']);
        $this->assertNull($skills['unit-spec-review']['version']);
    }

    public function testAnUnconfiguredAgentHasAnEmptySpec(): void
    {
        $agent = $this->createAgent('unit-agent-spec-empty@gaia.test', []);

        $spec = (new AgentSpec())->forAgent($agent->id);

        $this->assertConformsToSchema($spec);
        $this->assertNull($spec['agent']['sponsorId']);
        $this->assertSame('{}', json_encode($spec['config']['modelConfig']));
        $this->assertNull($spec['config']['maxTurns']);
        $this->assertNull($spec['config']['permissionMode']);
        $this->assertSame([], $spec['toolGrants']);
        $this->assertSame([], $spec['skills']);
    }

    public function testLeavesOutDeletedSkillsAndRevokedSkillGrants(): void
    {
        $agent = $this->createAgent('unit-agent-spec-del@gaia.test', []);
        $kept = $this->grantSkill($agent, 'kept', '# Kept', null);
        $deletedSkill = $this->grantSkill($agent, 'deleted-skill', '# Gone', null);
        $revoked = $this->grantSkill($agent, 'revoked-grant', '# Revoked', null);
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute('UPDATE skills SET deleted = TRUE WHERE id = ?', [$deletedSkill]);
        $db->execute('UPDATE agent_skills SET deleted = TRUE WHERE "skillId" = ?', [$revoked]);

        $spec = (new AgentSpec())->forAgent($agent->id);

        $this->assertSame([$kept], array_column($spec['skills'], 'id'));
    }

    public function testNeverReturnsServerEndpointsOrSecrets(): void
    {
        $agent = $this->createAgent('unit-agent-spec-sec@gaia.test', []);
        $this->createServer(self::SERVER_ID, 'p4m-tracker');
        $this->grantTool($agent, 'server', self::SERVER_ID, null, 'allow');

        $json = json_encode((new AgentSpec())->forAgent($agent->id));

        $this->assertStringNotContainsString('unit-secret-endpoint', $json);
        $this->assertStringNotContainsString('unit-secret-command', $json);
        $this->assertStringNotContainsString('unit-secret-spec', $json);
    }

    public function testThereIsNoSpecForAMissingOrDeletedAgent(): void
    {
        $agent = $this->createAgent('unit-agent-spec-gone@gaia.test', []);
        \Phalcon\Di::getDefault()->get('db')->execute('UPDATE agents SET deleted = TRUE WHERE id = ?', [$agent->id]);

        $this->assertNull((new AgentSpec())->forAgent($agent->id));
        $this->assertNull((new AgentSpec())->forAgent('unit-spec-no-such-agent'));
    }

    public function testTheSchemaCheckRejectsADocumentOffTheSchema(): void
    {
        $agent = $this->createAgent('unit-agent-spec-off@gaia.test', []);
        $spec = (new AgentSpec())->forAgent($agent->id);
        $spec['config']['maxTurns'] = 0;
        $spec['extra'] = true;
        unset($spec['skills']);

        $violations = JsonSchemaSubset::violations(json_decode(json_encode($spec)), $this->schema());

        $this->assertContains("$: missing 'skills'", $violations);
        $this->assertContains("$: unexpected 'extra'", $violations);
        $this->assertContains('$.config.maxTurns: 0 is below the minimum 1', $violations);
    }

    /**
     * @param array|null $spec
     */
    private function assertConformsToSchema($spec)
    {
        $this->assertIsArray($spec);
        $violations = JsonSchemaSubset::violations(json_decode(json_encode($spec)), $this->schema());
        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * @return object
     */
    private function schema()
    {
        return JsonSchemaSubset::load(APP_PATH . '/docs/schemas/agent-spec.schema.json');
    }

    /**
     * @param  string $email
     * @param  array  $config
     * @return Agent
     */
    private function createAgent($email, array $config)
    {
        return (new AgentLifecycle())->create(['name' => 'Unit Spec Agent', 'email' => $email] + $config);
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

    private function grantTool(Agent $agent, $suffix, $serverId, $toolId, $rule)
    {
        $grant = new Agenttool();
        $grant->id = 'unit-spec-grant-' . $suffix;
        $grant->agentId = $agent->id;
        $grant->toolServerId = $serverId;
        $grant->toolId = $toolId;
        $grant->permissionRule = $rule;
        $this->assertTrue($grant->save(), implode(' ', $grant->getMessages()));
    }

    /**
     * Create a skill and grant it to the agent.
     *
     * @return string The skill id
     */
    private function grantSkill(Agent $agent, $name, $content, $version)
    {
        $skill = new Skill();
        $skill->id = 'unit-spec-skill-' . $name;
        $skill->name = 'unit-spec-' . $name;
        $skill->description = 'Use for ' . $name;
        $skill->content = $content;
        $skill->version = $version;
        $this->assertTrue($skill->save(), implode(' ', $skill->getMessages()));

        $grant = new Agentskill();
        $grant->id = 'unit-spec-agentskill-' . $name;
        $grant->agentId = $agent->id;
        $grant->skillId = $skill->id;
        $this->assertTrue($grant->save(), implode(' ', $grant->getMessages()));

        return $skill->id;
    }

    private static function purge()
    {
        $db = \Phalcon\Di::getDefault()->get('db');
        $db->execute("DELETE FROM agent_skills WHERE id LIKE 'unit-spec-%'");
        $db->execute("DELETE FROM skills WHERE id LIKE 'unit-spec-%'");
        $db->execute("DELETE FROM agent_tools WHERE id LIKE 'unit-spec-%'");
        $db->execute("DELETE FROM tools WHERE id LIKE 'unit-spec-%'");
        $db->execute("DELETE FROM tool_servers WHERE id LIKE 'unit-spec-%'");
        AgentTestFixtures::purge();
    }
}
