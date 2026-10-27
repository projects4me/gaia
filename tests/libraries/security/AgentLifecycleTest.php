<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Agents\AgentLifecycle;
use Gaia\Libraries\Agents\AgentUserPolicy;
use Gaia\MVC\Models\Agent;
use Gaia\MVC\Models\User;

/**
 * Tests that an agent's users row and agents row are created and deleted
 * together, and the rules the user endpoints apply to agent users.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentLifecycleTest extends TestCase
{
    /**
     * Human user the agents are created by and sponsored by.
     */
    public const SPONSOR_ID = 'test-user-agent-sponsor';

    public static function setUpBeforeClass(): void
    {
        global $currentUser;

        AgentTestFixtures::ensureHumanUser(self::SPONSOR_ID, 'agent-sponsor@gaia.test');
        $currentUser = User::findFirstById(self::SPONSOR_ID);
    }

    public static function tearDownAfterClass(): void
    {
        AgentTestFixtures::purge();
        AgentTestFixtures::deleteUser(self::SPONSOR_ID);
    }

    protected function setUp(): void
    {
        AgentTestFixtures::purge();
    }

    public function testCreateMakesAnAgentUserAndItsAgentRow(): void
    {
        $agent = (new AgentLifecycle())->create([
            'name' => 'Unit Agent',
            'email' => 'unit-agent-create@gaia.test',
            'purpose' => 'Writes code',
            'sponsorId' => self::SPONSOR_ID,
            'modelConfig' => ['simple' => 'sonnet', 'complex' => 'opus'],
            'maxTurns' => 40,
            'permissionMode' => 'acceptEdits',
            'userId' => 'ignored-user-id',
        ]);

        $stored = Agent::findFirstById($agent->id);
        $this->assertNotFalse($stored);
        $this->assertSame('Unit Agent', $stored->name);
        $this->assertSame(self::SPONSOR_ID, $stored->sponsorId);
        $this->assertSame(['simple' => 'sonnet', 'complex' => 'opus'], json_decode($stored->modelConfig, true));
        $this->assertNotSame('ignored-user-id', $stored->userId);

        $user = User::findFirstById($stored->userId);
        $this->assertNotFalse($user);
        $this->assertTrue($user->isAgent());
        $this->assertSame('Unit Agent', $user->name);
        $this->assertSame('unit-agent-create@gaia.test', $user->email);
        $this->assertSame(AgentLifecycle::DEFAULT_ACCOUNT_STATUS, $user->accountStatus);
        $this->assertNotEmpty($user->password);
        $this->assertFalse(password_verify('', $user->password), 'An agent user has no usable password');
    }

    public function testCreateRollsBackTheUserWhenTheAgentIsInvalid(): void
    {
        try {
            (new AgentLifecycle())->create([
                'name' => 'Unit Agent Bad Config',
                'email' => 'unit-agent-bad-config@gaia.test',
                'modelConfig' => 'not json',
            ]);
            $this->fail('An invalid modelConfig must be rejected');
        } catch (\Gaia\Exception\Permission $e) {
            $this->assertSame('modelConfig must be a JSON object', $e->getMessage());
        }

        $this->assertNull(User::findFirstByEmail('unit-agent-bad-config@gaia.test'));
    }

    public function testCreateRejectsATakenEmail(): void
    {
        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage('A user with this email already exists.');

        (new AgentLifecycle())->create(['name' => 'Unit Agent Taken', 'email' => 'agent-sponsor@gaia.test']);
    }

    public function testCreateRejectsAnUnknownSponsor(): void
    {
        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage('The sponsor of an agent must be an existing user.');

        (new AgentLifecycle())->create([
            'name' => 'Unit Agent Orphan',
            'email' => 'unit-agent-orphan@gaia.test',
            'sponsorId' => 'no-such-user',
        ]);
    }

    public function testCreateRejectsANonPositiveMaxTurns(): void
    {
        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage('maxTurns must be a positive whole number');

        (new AgentLifecycle())->create([
            'name' => 'Unit Agent Zero Turns',
            'email' => 'unit-agent-zero-turns@gaia.test',
            'maxTurns' => 0,
        ]);
    }

    public function testTheUserOfAnAgentCannotBeChanged(): void
    {
        $agent = (new AgentLifecycle())->create(['name' => 'Unit Agent Fixed', 'email' => 'unit-agent-fixed@gaia.test']);

        $stored = Agent::findFirstById($agent->id);
        $stored->userId = self::SPONSOR_ID;

        $this->assertFalse($stored->save());
    }

    public function testDeletingEitherRowDeletesTheOther(): void
    {
        $lifecycle = new AgentLifecycle();
        $agent = $lifecycle->create(['name' => 'Unit Agent Gone', 'email' => 'unit-agent-gone@gaia.test']);
        $userId = $agent->userId;

        $lifecycle->deleteAgentOfUser($userId);
        $this->assertNull(Agent::findForUser($userId));

        $lifecycle->deleteUser($userId);
        $this->assertSame(1, (int) User::findFirstById($userId)->deleted);
    }

    public function testSyncUserNameCopiesTheAgentName(): void
    {
        $lifecycle = new AgentLifecycle();
        $agent = $lifecycle->create(['name' => 'Unit Agent Old', 'email' => 'unit-agent-rename@gaia.test']);

        $agent->name = 'Unit Agent New';
        $lifecycle->syncUserName($agent);

        $this->assertSame('Unit Agent New', User::findFirstById($agent->userId)->name);
    }

    public function testUserEndpointsCreateOnlyHumans(): void
    {
        $policy = new AgentUserPolicy();
        $policy->assertCreatable(['name' => 'Person', 'type' => 'Human']);
        $policy->assertCreatable(['name' => 'Person']);

        $this->expectException(\Gaia\Exception\Permission::class);
        $policy->assertCreatable(['name' => 'Fake agent', 'type' => 'Agent']);
    }

    public function testTheTypeOfAUserCannotChange(): void
    {
        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage('The type of a user cannot be changed.');

        (new AgentUserPolicy())->assertUpdatable(User::findFirstById(self::SPONSOR_ID), ['type' => 'Agent']);
    }

    public function testAnAgentUserCannotGetAPassword(): void
    {
        $agent = (new AgentLifecycle())->create(['name' => 'Unit Agent Locked', 'email' => 'unit-agent-locked@gaia.test']);
        $policy = new AgentUserPolicy();

        $policy->assertUpdatable(User::findFirstById(self::SPONSOR_ID), ['password' => 'a-new-secret']);

        $this->expectException(\Gaia\Exception\Permission::class);
        $this->expectExceptionMessage('An agent user cannot have a password.');
        $policy->assertUpdatable(User::findFirstById($agent->userId), ['password' => 'a-new-secret']);
    }
}
