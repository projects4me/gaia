<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\MVC\Models\Behaviors\issueAgentWorkspaceBehavior;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentWorkspaceFakeModel.php';

/**
 * Unit tests for issueAgentWorkspaceBehavior (Workspaces D-006). The
 * refusal of an agent outside its workspace needs the database and is
 * covered by the api-tester client cases; the rule itself by
 * AgentWorkspaceTest.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueAgentWorkspaceBehaviorTest extends TestCase
{
    /**
     * An issue without an assignee is not checked.
     *
     * @return void
     */
    public function testAnIssueWithoutAssigneePasses()
    {
        $model = AgentWorkspaceFakeModel::make(['assignee' => null, 'projectId' => 'p1']);

        $this->assertNotFalse((new issueAgentWorkspaceBehavior())->notify('beforeValidationOnCreate', $model));
        $this->assertSame([], $model->messages);
    }

    /**
     * An update that changes neither assignee nor project is not checked.
     *
     * @return void
     */
    public function testAnUpdateKeepingAssigneeAndProjectPasses()
    {
        $model = AgentWorkspaceFakeModel::make(
            ['assignee' => 'u1', 'projectId' => 'p1'],
            ['assignee' => 'u1', 'projectId' => 'p1']
        );

        $this->assertNotFalse((new issueAgentWorkspaceBehavior())->notify('beforeValidationOnUpdate', $model));
    }
}
