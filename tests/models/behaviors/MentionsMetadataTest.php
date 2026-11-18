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
 * The models that record mentions name their text fields in metadata, and an
 * update of that field (as the workflow Set field action makes: write the
 * attribute, then save) reaches the recorder.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MentionsMetadataTest extends TestCase
{
    /**
     * @return array
     */
    public function mentionModels()
    {
        return [
            'issue description' => ['Issue', 'description'],
            'comment text' => ['Comment', 'comment'],
            'thread description' => ['Conversationroom', 'description'],
        ];
    }

    /**
     * @dataProvider mentionModels
     * @param  string $name
     * @param  string $field
     * @return void
     */
    public function testModelRecordsMentionsOfItsTextField($name, $field)
    {
        $metadata = $this->metadata($name);

        $this->assertContains('mentionsBehavior', $metadata['behaviors']);
        $this->assertSame([$field], $metadata['mentions']['fields']);
        $this->assertArrayHasKey($field, $metadata['fields']);
    }

    /**
     * Setting an issue's description through a workflow, then saving, records its mentions.
     *
     * @return void
     */
    public function testSetFieldOnAnIssueDescriptionRecordsTheMention()
    {
        global $currentUser;
        $currentUser = (object) ['id' => 'actor'];

        $model = $this->getMockBuilder(ModelInterface::class)
            ->addMethods(['getMetaData', 'getOldSnapshotData'])
            ->getMockForAbstractClass();
        $model->method('getMetaData')->willReturn($this->metadata('Issue'));
        $model->method('getOldSnapshotData')->willReturn(['description' => 'before']);
        $model->description = 'ping @[user-1]';

        $recorder = $this->createMock(MentionRecorder::class);
        $recorder->expects($this->once())->method('record')->with($model, ['description'], 'actor');

        (new mentionsBehavior($recorder))->notify('afterUpdate', $model);
        $currentUser = null;
    }

    /**
     * @param  string $name
     * @return array
     */
    private function metadata($name)
    {
        $models = [];
        require dirname(__DIR__, 3) . '/app/metadata/model/' . $name . '.php';

        return $models[$name];
    }
}
