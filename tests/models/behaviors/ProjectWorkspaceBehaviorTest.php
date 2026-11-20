<?php

namespace Gaia\Tests\Models\Behaviors;

use Gaia\MVC\Models\Behaviors\projectWorkspaceBehavior;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for projectWorkspaceBehavior: a project cannot be moved to
 * another workspace (Workspaces D-002).
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
     * A save that changes the workspace is refused with a message on the field.
     *
     * @return void
     */
    public function testChangingTheWorkspaceIsRefused()
    {
        $model = $this->model(['workspaceId' => 'w1'], 'w2');
        $model->expects($this->once())
            ->method('appendMessage')
            ->with($this->callback(function (Message $message) {
                return $message->getField() === 'workspaceId'
                    && $message->getMessage() === 'Moving a project is not supported yet.';
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
        $model->method('getSnapshotData')->willReturn($snapshot);
        $model->workspaceId = $workspaceId;

        return $model;
    }
}
