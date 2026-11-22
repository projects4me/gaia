<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Workspaces\RepositoryUrl;
use Gaia\MVC\Models\Behaviors\repositoryBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for repositoryBehavior: an existing workspace, an optional project
 * of that workspace, a credential-free git URL, a default base branch, a name
 * unique in the workspace and a workspace that changes only with its project.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RepositoryBehaviorTest extends TestCase
{
    use WorkflowModelStub;

    private const VALID = [
        'workspaceId' => 'ws-1',
        'name' => 'gaia',
        'url' => 'https://github.com/projects4me/gaia.git',
        'baseBranch' => 'dev',
    ];

    public function testCreateAcceptsARepository()
    {
        $model = $this->model(self::VALID);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame([], $model->messages);
    }

    public function testCreateDefaultsTheBaseBranchToMain()
    {
        $model = $this->model(array_merge(self::VALID, ['baseBranch' => ' ']));

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame('main', $model->baseBranch);
    }

    public function testCreateRejectsAMissingWorkspace()
    {
        $model = $this->model(self::VALID);

        $this->assertFalse($this->behavior(false)->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['workspaceId must be the id of an existing workspace'], $model->messages);
    }

    public function testCreateRejectsCredentialsInTheUrl()
    {
        $model = $this->model(array_merge(self::VALID, ['url' => 'https://user:token@github.com/a/b.git']));

        $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame([RepositoryUrl::CREDENTIALS_ERROR], $model->messages);
    }

    public function testCreateRejectsATakenName()
    {
        $model = $this->model(self::VALID);

        $this->assertFalse($this->behavior(true, true)->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['A repository named gaia already exists in this workspace'], $model->messages);
    }

    public function testUpdateRejectsAWorkspaceChange()
    {
        $model = $this->model(array_merge(self::VALID, ['id' => 'r-1', 'workspaceId' => 'ws-2']), ['workspaceId' => 'ws-1']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['The workspace of a repository cannot be changed'], $model->messages);
    }

    public function testUpdateAcceptsTheSameWorkspace()
    {
        $model = $this->model(array_merge(self::VALID, ['id' => 'r-1']), ['workspaceId' => 'ws-1']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
    }

    public function testCreateAcceptsAProjectOfTheWorkspace()
    {
        $model = $this->model(array_merge(self::VALID, ['projectId' => 'p-1']));

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame([], $model->messages);
        $this->assertSame('p-1', $model->projectId);
    }

    public function testCreateStoresAnEmptyProjectAsWorkspaceWide()
    {
        $model = $this->model(array_merge(self::VALID, ['projectId' => ' ']));

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertNull($model->projectId);
    }

    public function testCreateRejectsAProjectOfAnotherWorkspace()
    {
        $model = $this->model(array_merge(self::VALID, ['projectId' => 'p-1']));

        $this->assertFalse($this->behavior(true, false, 'ws-2')->notify('beforeValidationOnCreate', $model));
        $this->assertSame([repositoryBehavior::PROJECT_ERROR], $model->messages);
    }

    public function testCreateRejectsAnUnknownProject()
    {
        $model = $this->model(array_merge(self::VALID, ['projectId' => 'p-missing']));

        $this->assertFalse($this->behavior(true, false, null)->notify('beforeValidationOnCreate', $model));
        $this->assertSame([repositoryBehavior::PROJECT_ERROR], $model->messages);
    }

    public function testUpdateRejectsAChangeToAProjectOfAnotherWorkspace()
    {
        $model = $this->model(array_merge(self::VALID, ['id' => 'r-1', 'projectId' => 'p-2']), ['workspaceId' => 'ws-1', 'projectId' => 'p-1']);

        $this->assertFalse($this->behavior(true, false, 'ws-2')->notify('beforeValidationOnUpdate', $model));
        $this->assertSame([repositoryBehavior::PROJECT_ERROR], $model->messages);
    }

    public function testUpdateClearsTheProject()
    {
        $model = $this->model(array_merge(self::VALID, ['id' => 'r-1', 'projectId' => '']), ['workspaceId' => 'ws-1', 'projectId' => 'p-1']);

        $this->assertNotFalse($this->behavior(true, false, null)->notify('beforeValidationOnUpdate', $model));
        $this->assertNull($model->projectId);
    }

    public function testUpdateKeepingTheProjectDoesNotCheckIt()
    {
        $model = $this->model(array_merge(self::VALID, ['id' => 'r-1', 'projectId' => 'p-1']), ['workspaceId' => 'ws-1', 'projectId' => 'p-1']);

        $this->assertNotFalse($this->behavior(true, false, null)->notify('beforeValidationOnUpdate', $model));
        $this->assertSame([], $model->messages);
    }

    public function testUpdateMovesTheWorkspaceWithItsProject()
    {
        $model = $this->getMockBuilder(DynamicModelStub::class)
            ->addMethods(['getSnapshotData', 'readAttribute', 'writeAttribute', 'isMovingWithProject'])
            ->getMockForAbstractClass();
        $model->messages = [];
        $model->method('getSnapshotData')->willReturn(['workspaceId' => 'ws-1', 'projectId' => 'p-1']);
        $model->method('readAttribute')->willReturnCallback(function ($field) use ($model) {
            return isset($model->{$field}) ? $model->{$field} : null;
        });
        $model->method('isMovingWithProject')->willReturn(true);
        $model->method('appendMessage')->willReturnCallback(function ($message) use ($model) {
            $model->messages[] = $message->getMessage();
            return $model;
        });
        foreach (array_merge(self::VALID, ['id' => 'r-1', 'workspaceId' => 'ws-2', 'projectId' => 'p-1']) as $field => $value) {
            $model->{$field} = $value;
        }

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame([], $model->messages);
    }

    public function testUpdateIgnoresAMoveFlagSentAsAnAttribute()
    {
        $model = $this->model(
            array_merge(self::VALID, ['id' => 'r-1', 'workspaceId' => 'ws-2', 'projectId' => 'p-1', 'movingWithProject' => true]),
            ['workspaceId' => 'ws-1', 'projectId' => 'p-1']
        );

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['The workspace of a repository cannot be changed'], $model->messages);
    }

    public function testDeleteNeedsNoChecks()
    {
        $model = $this->model(array_merge(self::VALID, ['id' => 'r-1', 'url' => 'bad', 'isDeleting' => true]), ['workspaceId' => 'ws-1']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * @param  bool $workspaceExists
     * @param  bool        $nameTaken
     * @param  string|null $projectWorkspaceId the workspace of any project, null for none
     * @return repositoryBehavior
     */
    private function behavior($workspaceExists = true, $nameTaken = false, $projectWorkspaceId = 'ws-1')
    {
        return new class ($workspaceExists, $nameTaken, $projectWorkspaceId) extends repositoryBehavior {
            private $workspaceExists;
            private $nameTaken;
            private $projectWorkspace;

            public function __construct($workspaceExists, $nameTaken, $projectWorkspace)
            {
                $this->workspaceExists = $workspaceExists;
                $this->nameTaken = $nameTaken;
                $this->projectWorkspace = $projectWorkspace;
            }

            protected function projectWorkspaceId($projectId)
            {
                return $this->projectWorkspace;
            }

            protected function workspaceExists($workspaceId)
            {
                return $this->workspaceExists;
            }

            protected function nameTaken($workspaceId, $name, $exceptId)
            {
                return $this->nameTaken;
            }
        };
    }
}
