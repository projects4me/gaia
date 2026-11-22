<?php

namespace Gaia\Tests\Libraries\Workspaces;

use Gaia\Libraries\Workspaces\WorkspaceActivity;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see WorkspaceActivity} (Workspaces D-018): which rows a
 * workspace change records, with names looked up in memory.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceActivityTest extends TestCase
{
    /**
     * @return void
     */
    public function testCreatedRecordsTheWorkspaceCreated()
    {
        $activity = new RecordingWorkspaceActivity();

        $activity->created((object) ['id' => 'w1', 'name' => 'Core']);

        $this->assertSame([[
            'relatedTo' => 'workspace',
            'relatedId' => 'w1',
            'type' => 'created',
            'description' => 'Workspace <b>Core</b> created',
        ]], $activity->rows);
    }

    /**
     * One row per recorded field that changed; other fields and unchanged
     * values record nothing.
     *
     * @return void
     */
    public function testUpdatedRecordsOneRowPerChangedField()
    {
        $activity = new RecordingWorkspaceActivity();

        $activity->updated((object) ['id' => 'w1'], [
            'name' => ['old' => 'Core', 'new' => 'Platform'],
            'shortCode' => ['old' => 'core', 'new' => 'core'],
            'description' => ['old' => null, 'new' => 'All of it'],
            'dateModified' => ['old' => '2026-01-01', 'new' => '2026-01-02'],
        ]);

        $this->assertSame(['updated', 'updated'], array_column($activity->rows, 'type'));
        $this->assertSame(
            ['Name changed from <b>Core</b> to <b>Platform</b>', 'Description updated'],
            array_column($activity->rows, 'description')
        );
    }

    /**
     * @return void
     */
    public function testUpdatedWithoutAuditRecordsNothing()
    {
        $activity = new RecordingWorkspaceActivity();

        $activity->updated((object) ['id' => 'w1'], null);

        $this->assertSame([], $activity->rows);
    }

    /**
     * @return void
     */
    public function testRuntimeBindingIsDescribedAsBoundUnboundOrChanged()
    {
        $activity = new RecordingWorkspaceActivity();
        $workspace = (object) ['id' => 'w1'];

        $activity->updated($workspace, ['runtimeId' => ['old' => null, 'new' => 'r1']]);
        $activity->updated($workspace, ['runtimeId' => ['old' => 'r1', 'new' => '']]);
        $activity->updated($workspace, ['runtimeId' => ['old' => 'r1', 'new' => 'r2']]);

        $this->assertSame([
            'Runtime <b>Runner r1</b> bound',
            'Runtime <b>Runner r1</b> unbound',
            'Runtime changed from <b>Runner r1</b> to <b>Runner r2</b>',
        ], array_column($activity->rows, 'description'));
    }

    /**
     * @return void
     */
    public function testProjectAddedIsARelatedProjectRow()
    {
        $activity = new RecordingWorkspaceActivity();

        $activity->projectAdded('w1', (object) ['id' => 'p1', 'name' => 'Gaia']);

        $this->assertSame([[
            'relatedTo' => 'workspace',
            'relatedId' => 'w1',
            'type' => 'related',
            'relatedActivityModule' => 'project',
            'relatedActivity' => 'created',
            'description' => 'Project <b>Gaia</b> added',
        ]], $activity->rows);
    }

    /**
     * A move records a row in the workspace left and the one joined.
     *
     * @return void
     */
    public function testProjectMovedRecordsInBothWorkspaces()
    {
        $activity = new RecordingWorkspaceActivity();

        $activity->projectMoved((object) ['id' => 'p1', 'name' => 'Gaia'], 'w1', 'w2');

        $this->assertSame(['w1', 'w2'], array_column($activity->rows, 'relatedId'));
        $this->assertSame(['movedOut', 'movedIn'], array_column($activity->rows, 'relatedActivity'));
        $this->assertSame([
            'Project <b>Gaia</b> moved to <b>Workspace w2</b>',
            'Project <b>Gaia</b> moved in from <b>Workspace w1</b>',
        ], array_column($activity->rows, 'description'));
    }

    /**
     * A legacy project without a workspace only records its arrival.
     *
     * @return void
     */
    public function testLegacyProjectMovedRecordsOnlyTheArrival()
    {
        $activity = new RecordingWorkspaceActivity();

        $activity->projectMoved((object) ['id' => 'p1', 'name' => 'Gaia'], null, 'w2');

        $this->assertSame(['w2'], array_column($activity->rows, 'relatedId'));
        $this->assertSame(['Project <b>Gaia</b> moved in'], array_column($activity->rows, 'description'));
    }

    /**
     * @return void
     */
    public function testMemberAddedAndRemovedAreRelatedMembershipRows()
    {
        $activity = new RecordingWorkspaceActivity();

        $activity->memberAdded('w1', 'u1');
        $activity->memberRemoved('w1', 'u1');

        $this->assertSame(['membership', 'membership'], array_column($activity->rows, 'relatedActivityModule'));
        $this->assertSame(['created', 'deleted'], array_column($activity->rows, 'relatedActivity'));
        $this->assertSame([
            'New member <b>User u1</b> has been added to the workspace',
            'Member <b>User u1</b> has been removed from the workspace',
        ], array_column($activity->rows, 'description'));
    }

    /**
     * Names are escaped, since the description is HTML.
     *
     * @return void
     */
    public function testNamesAreEscaped()
    {
        $activity = new RecordingWorkspaceActivity();

        $activity->projectAdded('w1', (object) ['name' => '<script>x</script>']);

        $this->assertSame(
            'Project <b>&lt;script&gt;x&lt;/script&gt;</b> added',
            $activity->rows[0]['description']
        );
    }

    /**
     * Nothing is recorded for a missing workspace.
     *
     * @return void
     */
    public function testBlankWorkspaceRecordsNothing()
    {
        $activity = new RecordingWorkspaceActivity();

        $activity->projectAdded(null, (object) ['name' => 'Gaia']);
        $activity->memberAdded('', 'u1');
        $activity->created((object) ['name' => 'Core']);

        $this->assertSame([], $activity->rows);
    }

    /**
     * A failing name lookup is logged and never thrown: recording can't fail
     * the write that caused it.
     *
     * @return void
     */
    public function testFailingLookupIsLoggedNotThrown()
    {
        $activity = new FailingLookupWorkspaceActivity();

        $activity->memberAdded('w1', 'u1');
        $activity->projectMoved((object) ['name' => 'Gaia'], 'w1', 'w2');
        $activity->updated((object) ['id' => 'w1'], ['runtimeId' => ['old' => '', 'new' => 'r1']]);

        $this->assertSame([], $activity->rows);
        $this->assertCount(3, $activity->errors);
    }
}

/**
 * Keeps the rows instead of saving them and names records after their id.
 */
class RecordingWorkspaceActivity extends WorkspaceActivity
{
    /**
     * @var array
     */
    public $rows = [];

    protected function write(array $row)
    {
        $this->rows[] = $row;
    }

    protected function userName($userId)
    {
        return 'User ' . $userId;
    }

    protected function workspaceName($workspaceId)
    {
        return 'Workspace ' . $workspaceId;
    }

    protected function runtimeName($runtimeId)
    {
        return 'Runner ' . $runtimeId;
    }
}

/**
 * Its name lookups fail, as a database error would; errors are kept.
 */
class FailingLookupWorkspaceActivity extends RecordingWorkspaceActivity
{
    /**
     * @var array
     */
    public $errors = [];

    protected function userName($userId)
    {
        throw new \RuntimeException('db down');
    }

    protected function workspaceName($workspaceId)
    {
        throw new \RuntimeException('db down');
    }

    protected function runtimeName($runtimeId)
    {
        throw new \RuntimeException('db down');
    }

    protected function logError($message)
    {
        $this->errors[] = $message;
    }
}
