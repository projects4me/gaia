<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Workspaces\RepositoryUrl;
use Gaia\MVC\Models\Behaviors\repositoryBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for repositoryBehavior: an existing workspace, a credential-free
 * git URL, a default base branch, a name unique in the workspace and a
 * workspace that never changes.
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

    public function testDeleteNeedsNoChecks()
    {
        $model = $this->model(array_merge(self::VALID, ['id' => 'r-1', 'url' => 'bad', 'isDeleting' => true]), ['workspaceId' => 'ws-1']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * @param  bool $workspaceExists
     * @param  bool $nameTaken
     * @return repositoryBehavior
     */
    private function behavior($workspaceExists = true, $nameTaken = false)
    {
        return new class ($workspaceExists, $nameTaken) extends repositoryBehavior {
            private $workspaceExists;
            private $nameTaken;

            public function __construct($workspaceExists, $nameTaken)
            {
                $this->workspaceExists = $workspaceExists;
                $this->nameTaken = $nameTaken;
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
