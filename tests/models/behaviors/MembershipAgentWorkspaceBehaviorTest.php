<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\MVC\Models\Behaviors\membershipAgentWorkspaceBehavior;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentWorkspaceFakeModel.php';

/**
 * Unit tests for membershipAgentWorkspaceBehavior (Workspaces D-006). The
 * refusal of an agent outside its workspace needs the database and is
 * covered by the api-tester client cases; the rule itself by
 * AgentWorkspaceTest.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MembershipAgentWorkspaceBehaviorTest extends TestCase
{
    /**
     * A membership without a user is not checked.
     *
     * @return void
     */
    public function testAMembershipWithoutUserPasses()
    {
        $model = AgentWorkspaceFakeModel::make(['userId' => null, 'projectId' => 'p1']);

        $this->assertNotFalse((new membershipAgentWorkspaceBehavior())->notify('beforeValidationOnCreate', $model));
        $this->assertSame([], $model->messages);
    }
}
