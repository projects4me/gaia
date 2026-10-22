<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\Models\Behaviors\ideaScreensBehavior;
use Phalcon\Mvc\ModelInterface;

/**
 * Unit tests for ideaScreensBehavior: screens of an idea message must be
 * uploads related to the message's idea.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IdeaScreensBehaviorTest extends TestCase
{
    /**
     * A screens list yields its distinct upload ids.
     *
     * @return void
     */
    public function testUploadIdsOfAScreensList()
    {
        $behavior = new ideaScreensBehavior();
        $screens = json_encode([
            ['uploadId' => 'u1', 'key' => 'A1', 'title' => 'Board', 'version' => 1, 'width' => 1280, 'height' => 800],
            ['uploadId' => 'u2', 'key' => 'A2'],
            ['uploadId' => 'u1', 'key' => 'A1', 'version' => 2],
        ]);

        $this->assertSame(['u1', 'u2'], $behavior->uploadIds($screens));
        $this->assertSame([], $behavior->uploadIds('[]'));
    }

    /**
     * Anything but a list of objects with an uploadId is malformed.
     *
     * @return void
     */
    public function testMalformedScreensHaveNoUploadIds()
    {
        $behavior = new ideaScreensBehavior();

        $this->assertNull($behavior->uploadIds('not json'));
        $this->assertNull($behavior->uploadIds('{"uploadId":"u1"}'));
        $this->assertNull($behavior->uploadIds('[{"key":"A1"}]'));
        $this->assertNull($behavior->uploadIds('[{"uploadId":""}]'));
        $this->assertNull($behavior->uploadIds('["u1"]'));
    }

    /**
     * Screens of the idea's uploads are accepted; an array is stored as JSON.
     *
     * @return void
     */
    public function testCreateAcceptsUploadsOfTheIdea()
    {
        $behavior = $this->behaviorFinding(['u1']);
        $model = $this->model('idea-1', [['uploadId' => 'u1', 'key' => 'A1']]);
        $model->expects($this->never())->method('appendMessage');

        $this->assertTrue($behavior->notify('beforeValidationOnCreate', $model));
        $this->assertSame('[{"uploadId":"u1","key":"A1"}]', $model->screens);
    }

    /**
     * An upload that is not related to the idea cancels the save.
     *
     * @return void
     */
    public function testCreateRejectsUploadsOutsideTheIdea()
    {
        $behavior = $this->behaviorFinding(['u1']);
        $model = $this->model('idea-1', '[{"uploadId":"u1"},{"uploadId":"u9"}]');
        $model->expects($this->once())->method('appendMessage');

        $this->assertFalse($behavior->notify('beforeValidationOnCreate', $model));
    }

    /**
     * Malformed screens cancel the save; empty screens are allowed.
     *
     * @return void
     */
    public function testCreateRejectsMalformedAndAllowsEmptyScreens()
    {
        $behavior = $this->behaviorFinding([]);

        $malformed = $this->model('idea-1', '{"uploadId":"u1"}');
        $malformed->expects($this->once())->method('appendMessage');
        $this->assertFalse($behavior->notify('beforeValidationOnCreate', $malformed));

        $this->assertTrue($behavior->notify('beforeValidationOnCreate', $this->model('idea-1', null)));
        $this->assertTrue($behavior->notify('beforeValidationOnCreate', $this->model('idea-1', '')));
    }

    /**
     * Unchanged screens are not checked again on update.
     *
     * @return void
     */
    public function testUpdateSkipsUnchangedScreens()
    {
        $behavior = $this->behaviorFinding([]);
        $model = $this->model('idea-1', '[{"uploadId":"u1"}]');
        $model->method('getSnapshotData')->willReturn(['ideaId' => 'idea-1', 'screens' => '[{"uploadId":"u1"}]']);
        $model->expects($this->never())->method('appendMessage');

        $this->assertTrue($behavior->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * Changed screens are checked on update.
     *
     * @return void
     */
    public function testUpdateChecksChangedScreens()
    {
        $behavior = $this->behaviorFinding([]);
        $model = $this->model('idea-1', '[{"uploadId":"u2"}]');
        $model->method('getSnapshotData')->willReturn(['ideaId' => 'idea-1', 'screens' => '[{"uploadId":"u1"}]']);
        $model->expects($this->once())->method('appendMessage');

        $this->assertFalse($behavior->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * A behavior whose upload lookup finds the given ids.
     *
     * @param  array $found
     * @return ideaScreensBehavior
     */
    private function behaviorFinding(array $found)
    {
        $behavior = $this->getMockBuilder(ideaScreensBehavior::class)
            ->setMethods(['findIdeaUploadIds'])
            ->getMock();
        $behavior->method('findIdeaUploadIds')->willReturnCallback(
            function ($ideaId, array $uploadIds) use ($found) {
                return array_values(array_intersect($uploadIds, $found));
            }
        );
        return $behavior;
    }

    /**
     * An idea message with the given screens.
     *
     * @param  string $ideaId
     * @param  mixed  $screens
     * @return ModelInterface
     */
    private function model($ideaId, $screens)
    {
        $model = $this->getMockBuilder(ModelInterface::class)
            ->addMethods(['getSnapshotData'])
            ->getMockForAbstractClass();
        $model->ideaId = $ideaId;
        $model->screens = $screens;
        return $model;
    }
}
