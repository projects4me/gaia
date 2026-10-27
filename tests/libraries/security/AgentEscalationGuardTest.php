<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Agents\AgentLifecycle;
use Gaia\Libraries\Security\AgentEscalationGuard;
use Gaia\MVC\Models\User;
use Gaia\MVC\Models\Userrole;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Tests that an agent cannot change its own agent, user record, roles or the
 * permissions of its roles, while other agents and human users are not
 * affected.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentEscalationGuardTest extends TestCase
{
    /**
     * Human user the fixtures are created as.
     */
    public const HUMAN_ID = 'test-user-agent-guard-human';

    /**
     * Role the acting agent holds.
     */
    public const HELD_ROLE_ID = 'test-role-agent-guard-held';

    /**
     * @var \Gaia\MVC\Models\Agent
     */
    private $agent;

    /**
     * @var \Gaia\MVC\Models\Agent
     */
    private $otherAgent;

    /**
     * @var User
     */
    private $actor;

    /**
     * @var Userrole
     */
    private $heldRole;

    public static function setUpBeforeClass(): void
    {
        AgentTestFixtures::ensureHumanUser(self::HUMAN_ID, 'agent-guard-human@gaia.test');
    }

    public static function tearDownAfterClass(): void
    {
        AgentTestFixtures::purge();
        AgentTestFixtures::deleteUser(self::HUMAN_ID);
    }

    protected function setUp(): void
    {
        global $currentUser;

        AgentTestFixtures::purge();
        $currentUser = User::findFirstById(self::HUMAN_ID);

        $lifecycle = new AgentLifecycle();
        $this->agent = $lifecycle->create(['name' => 'Unit Agent Actor', 'email' => 'unit-agent-actor@gaia.test']);
        $this->otherAgent = $lifecycle->create(['name' => 'Unit Agent Other', 'email' => 'unit-agent-other@gaia.test']);
        $this->actor = User::findFirstById($this->agent->userId);

        $this->heldRole = new Userrole();
        $this->heldRole->id = create_guid();
        $this->heldRole->userId = $this->actor->id;
        $this->heldRole->roleId = self::HELD_ROLE_ID;
        $this->assertTrue($this->heldRole->save());
    }

    public function testAnAgentCannotChangeItsOwnAgent(): void
    {
        $this->expectException(\Gaia\Exception\Access::class);
        AgentEscalationGuard::assertNotOwnAgent($this->actor, $this->agent->id);
    }

    public function testAnAgentMayChangeAnotherAgent(): void
    {
        AgentEscalationGuard::assertNotOwnAgent($this->actor, $this->otherAgent->id);
        AgentEscalationGuard::assertNotOwnUser($this->actor, $this->otherAgent->userId);
        $this->addToAssertionCount(1);
    }

    public function testAnAgentCannotChangeItsOwnUserRecord(): void
    {
        $this->expectException(\Gaia\Exception\Access::class);
        AgentEscalationGuard::assertNotOwnUser($this->actor, $this->actor->id);
    }

    public function testAnAgentCannotAssignItselfARole(): void
    {
        $this->expectException(\Gaia\Exception\Access::class);
        AgentEscalationGuard::assertNotOwnRoleAssignment($this->actor, null, [
            'userId' => $this->actor->id,
            'roleId' => 'any-role',
        ]);
    }

    public function testAnAgentCannotChangeOrRemoveItsOwnRoleAssignment(): void
    {
        $this->expectException(\Gaia\Exception\Access::class);
        AgentEscalationGuard::assertNotOwnRoleAssignment($this->actor, $this->heldRole->id, ['roleId' => 'any-role']);
    }

    public function testAnAgentMayAssignRolesToOthers(): void
    {
        AgentEscalationGuard::assertNotOwnRoleAssignment($this->actor, null, [
            'userId' => $this->otherAgent->userId,
            'roleId' => self::HELD_ROLE_ID,
        ]);
        $this->addToAssertionCount(1);
    }

    public function testAnAgentCannotChangeThePermissionsOfARoleItHolds(): void
    {
        AgentEscalationGuard::assertNotOwnRole($this->actor, 'a-role-it-does-not-hold');

        $this->expectException(\Gaia\Exception\Access::class);
        AgentEscalationGuard::assertNotOwnRole($this->actor, self::HELD_ROLE_ID);
    }

    public function testAnAgentCannotWriteAPermissionOnARoleItHolds(): void
    {
        AgentEscalationGuard::assertNotOwnPermission($this->actor, null, 'a-role-it-does-not-hold');
        AgentEscalationGuard::assertNotOwnPermission($this->actor, 'no-such-permission', 'a-role-it-does-not-hold');

        $this->expectException(\Gaia\Exception\Access::class);
        AgentEscalationGuard::assertNotOwnPermission($this->actor, null, self::HELD_ROLE_ID);
    }

    public function testHumansAreNotAffected(): void
    {
        $human = User::findFirstById(self::HUMAN_ID);

        AgentEscalationGuard::assertNotOwnUser($human, $human->id);
        AgentEscalationGuard::assertNotOwnAgent($human, $this->agent->id);
        AgentEscalationGuard::assertNotOwnRoleAssignment($human, null, ['userId' => $human->id]);
        AgentEscalationGuard::assertNotOwnRole($human, self::HELD_ROLE_ID);
        AgentEscalationGuard::assertNotOwnPermission($human, null, self::HELD_ROLE_ID);
        $this->addToAssertionCount(1);
    }
}
