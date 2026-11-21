<?php

namespace Gaia\Tests\Libraries\Workspaces;

use Gaia\Libraries\Workspaces\AgentWorkspace;
use Gaia\Libraries\Workspaces\WorkspaceMemberships;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see AgentWorkspace} (Workspaces D-006).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentWorkspaceTest extends TestCase
{
    /**
     * An agent may work in a project of its own workspace only.
     *
     * @return void
     */
    public function testAgentMayWorkOnlyInItsWorkspace()
    {
        $rule = $this->rule(['agent1' => 'w1'], ['p1' => 'w1', 'p2' => 'w2']);

        $this->assertTrue($rule->mayWorkIn('agent1', 'p1'));
        $this->assertFalse($rule->mayWorkIn('agent1', 'p2'));
        $this->assertFalse($rule->mayWorkIn('agent1', 'unknown'));
    }

    /**
     * A person, and an agent without a workspace, are not restricted.
     *
     * @return void
     */
    public function testPeopleAndLegacyAgentsAreNotRestricted()
    {
        $rule = $this->rule([], ['p1' => 'w1']);

        $this->assertTrue($rule->mayWorkIn('person', 'p1'));
        $this->assertTrue($rule->mayWorkIn(null, 'p1'));
    }

    /**
     * Only a different stored workspace counts as a change.
     *
     * @return void
     */
    public function testIsChange()
    {
        $rule = $this->rule([], []);

        $this->assertFalse($rule->isChange(null, 'w1'));
        $this->assertFalse($rule->isChange(['workspaceId' => null], 'w1'));
        $this->assertFalse($rule->isChange(['workspaceId' => 'w1'], 'w1'));
        $this->assertTrue($rule->isChange(['workspaceId' => 'w1'], 'w2'));
    }

    /**
     * @param  array $agentWorkspaces user id => workspace id
     * @param  array $projectWorkspaces project id => workspace id
     * @return AgentWorkspace
     */
    private function rule(array $agentWorkspaces, array $projectWorkspaces)
    {
        $store = new class ($projectWorkspaces) extends WorkspaceMemberships {
            private $projects;

            public function __construct(array $projects)
            {
                $this->projects = $projects;
            }

            public function workspaceIdOf($projectId)
            {
                return $this->projects[$projectId] ?? null;
            }
        };

        return new class ($store, $agentWorkspaces) extends AgentWorkspace {
            private $agents;

            public function __construct($store, array $agents)
            {
                parent::__construct($store);
                $this->agents = $agents;
            }

            public function workspaceIdOfAgentUser($userId)
            {
                return $this->agents[$userId] ?? null;
            }
        };
    }
}
