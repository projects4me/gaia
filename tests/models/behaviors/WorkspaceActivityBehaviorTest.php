<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Workspaces\WorkspaceActivity;
use Gaia\MVC\Models\Behaviors\workspaceActivityBehavior;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AgentWorkspaceFakeModel.php';

/**
 * Unit tests for workspaceActivityBehavior: a workspace records its creation
 * and the changes auditBehavior collected (Workspaces D-019).
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceActivityBehaviorTest extends TestCase
{
    /**
     * @var array
     */
    private $calls = [];

    /**
     * @return void
     */
    public function testCreateRecordsTheWorkspaceCreated()
    {
        $model = AgentWorkspaceFakeModel::make(['id' => 'w1', 'name' => 'Core']);

        $this->behavior()->notify('afterCreate', $model);

        $this->assertSame([['created', $model, null]], $this->calls);
    }

    /**
     * An update hands over the audit of the save.
     *
     * @return void
     */
    public function testUpdateRecordsTheAuditedChanges()
    {
        $model = (new \ReflectionClass(AuditedWorkspaceFakeModel::class))->newInstanceWithoutConstructor();
        $model->audit = ['name' => ['old' => 'Core', 'new' => 'Platform']];

        $this->behavior()->notify('afterUpdate', $model);

        $this->assertSame([['updated', $model, $model->audit]], $this->calls);
    }

    /**
     * Other events record nothing.
     *
     * @return void
     */
    public function testOtherEventsRecordNothing()
    {
        $model = AgentWorkspaceFakeModel::make(['id' => 'w1']);

        $this->behavior()->notify('beforeValidationOnUpdate', $model);
        $this->behavior()->notify('afterDelete', $model);

        $this->assertSame([], $this->calls);
    }

    /**
     * The behavior with a recorder that keeps its calls.
     *
     * @return workspaceActivityBehavior
     */
    private function behavior()
    {
        $calls = &$this->calls;

        return new class ($calls) extends workspaceActivityBehavior {
            private $calls;

            public function __construct(array &$calls)
            {
                $this->calls = &$calls;
            }

            protected function activity()
            {
                $calls = &$this->calls;

                return new class ($calls) extends WorkspaceActivity {
                    private $calls;

                    public function __construct(array &$calls)
                    {
                        $this->calls = &$calls;
                    }

                    public function created($workspace)
                    {
                        $this->calls[] = ['created', $workspace, null];
                    }

                    public function updated($workspace, $audit)
                    {
                        $this->calls[] = ['updated', $workspace, $audit];
                    }
                };
            }
        };
    }
}

/**
 * A fake model with the audit auditBehavior leaves on a saved model.
 */
class AuditedWorkspaceFakeModel extends AgentWorkspaceFakeModel
{
    /**
     * @var array|null
     */
    public $audit;
}
