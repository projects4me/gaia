<?php

namespace Gaia\Tests\Models\Behaviors;

use Gaia\MVC\Models\Behaviors\projectWorkspaceBehavior;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for projectWorkspaceBehavior: a save that keeps the workspace
 * skips the move gate, and a project cannot be moved out of every workspace
 * (Workspaces D-011).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProjectWorkspaceBehaviorTest extends TestCase
{
    /**
     * A save that keeps the stored workspace goes through.
     *
     * @return void
     */
    public function testKeepingTheWorkspaceIsAllowed()
    {
        $model = $this->model(['workspaceId' => 'w1'], 'w1');
        $model->expects($this->never())->method('appendMessage');

        $this->assertTrue((new projectWorkspaceBehavior())->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * A save that keeps the workspace keeps the stored join date over the one sent (Workspaces D-016).
     *
     * @return void
     */
    public function testKeepingTheWorkspaceKeepsTheJoinDate()
    {
        $model = $this->model(['workspaceId' => 'w1', 'workspaceJoinedAt' => '2026-01-01 00:00:00'], 'w1');
        $model->workspaceJoinedAt = '2030-01-01 00:00:00';

        (new projectWorkspaceBehavior())->notify('beforeValidationOnUpdate', $model);

        $this->assertSame('2026-01-01 00:00:00', $model->workspaceJoinedAt);
    }

    /**
     * A save that clears the workspace is refused with a message on the field.
     *
     * @return void
     */
    public function testMovingToNoWorkspaceIsRefused()
    {
        $model = $this->model(['workspaceId' => 'w1'], '');
        $model->expects($this->once())
            ->method('appendMessage')
            ->with($this->callback(function (Message $message) {
                return $message->getField() === 'workspaceId'
                    && $message->getMessage() === 'A project must belong to a workspace.';
            }));

        $this->assertFalse((new projectWorkspaceBehavior())->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * @param  array  $snapshot
     * @param  string $workspaceId
     * @return ModelInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private function model(array $snapshot, $workspaceId)
    {
        $model = $this->getMockBuilder(\Phalcon\Mvc\Model::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSnapshotData', 'appendMessage'])
            ->getMock();
        // A stored join date: assigning null to a mocked model looks up relations through its models manager.
        $model->method('getSnapshotData')->willReturn($snapshot + ['workspaceJoinedAt' => '2026-01-01 00:00:00']);
        $model->id = 'p1';
        $model->workspaceId = $workspaceId;

        return $model;
    }
}
