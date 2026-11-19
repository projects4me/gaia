<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Mentions\MentionRecorder;
use Gaia\MVC\Models\Behaviors\mentionsBehavior;
use Phalcon\Mvc\ModelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for mentionsBehavior: the record goes to the recorder after a
 * create and after an update of a mentions field, and errors never fail the save.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MentionsBehaviorTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        global $currentUser;

        $currentUser = (object) ['id' => 'actor', 'name' => 'Actor'];
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        global $currentUser;

        $currentUser = null;
    }

    /**
     * A create hands the record, its mentions fields and the actor to the recorder.
     *
     * @return void
     */
    public function testCreateRecords()
    {
        $model = $this->model(['description' => 'x']);
        $recorder = $this->createMock(MentionRecorder::class);
        $recorder->expects($this->once())->method('record')->with($model, ['description'], 'actor');

        (new mentionsBehavior($recorder))->notify('afterCreate', $model);
    }

    /**
     * An update records when a mentions field changed.
     *
     * @return void
     */
    public function testUpdateOfAMentionsFieldRecords()
    {
        $model = $this->model(['description' => 'new', 'name' => 'n'], ['description' => 'old', 'name' => 'n']);
        $recorder = $this->createMock(MentionRecorder::class);
        $recorder->expects($this->once())->method('record')->with($model, ['description'], 'actor');

        (new mentionsBehavior($recorder))->notify('afterUpdate', $model);
    }

    /**
     * An update that leaves the mentions fields alone records nothing.
     *
     * @return void
     */
    public function testUpdateOfOtherFieldsDoesNotRecord()
    {
        $model = $this->model(['description' => 'same', 'name' => 'new'], ['description' => 'same', 'name' => 'old']);
        $recorder = $this->createMock(MentionRecorder::class);
        $recorder->expects($this->never())->method('record');

        (new mentionsBehavior($recorder))->notify('afterUpdate', $model);
    }

    /**
     * A model without mentions metadata, and other events, record nothing.
     *
     * @return void
     */
    public function testNothingWithoutMetadataOrForOtherEvents()
    {
        $recorder = $this->createMock(MentionRecorder::class);
        $recorder->expects($this->never())->method('record');
        $behavior = new mentionsBehavior($recorder);

        $behavior->notify('afterCreate', $this->model(['description' => 'x'], [], null));
        $behavior->notify('beforeValidationOnCreate', $this->model(['description' => 'x']));
        $behavior->notify('afterDelete', $this->model(['description' => 'x']));
    }

    /**
     * An error while recording is swallowed so the save still succeeds.
     *
     * @return void
     */
    public function testRecorderErrorDoesNotFailTheSave()
    {
        $recorder = $this->createMock(MentionRecorder::class);
        $recorder->method('record')->willThrowException(new \RuntimeException('db down'));

        $this->assertNull((new mentionsBehavior($recorder))->notify('afterCreate', $this->model(['description' => 'x'])));
    }

    /**
     * A model mock with mentions metadata and an old snapshot.
     *
     * @param  array      $values
     * @param  array      $oldSnapshot
     * @param  array|null $fields The mentions fields; null for no mentions metadata.
     * @return ModelInterface
     */
    private function model(array $values, array $oldSnapshot = [], $fields = ['description'])
    {
        $model = $this->getMockBuilder(DynamicModelStub::class)
            ->addMethods(['getMetaData', 'getOldSnapshotData'])
            ->getMockForAbstractClass();
        $model->method('getMetaData')->willReturn($fields === null ? ['tableName' => 'x'] : ['mentions' => ['fields' => $fields]]);
        $model->method('getOldSnapshotData')->willReturn($oldSnapshot);
        foreach ($values as $field => $value) {
            $model->{$field} = $value;
        }

        return $model;
    }
}
