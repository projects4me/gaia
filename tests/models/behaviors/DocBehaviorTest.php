<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\Models\Behaviors\docBehavior;
use Phalcon\Mvc\ModelInterface;

/**
 * Unit tests for docBehavior: only the stored owner or a user who may update
 * projects may lock or unlock a doc.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DocBehaviorTest extends TestCase
{
    /**
     * The owner may lock the doc.
     *
     * @return void
     */
    public function testOwnerMayLock()
    {
        $behavior = $this->behavior('owner-1', false);

        $this->assertTrue($behavior->notify('beforeValidationOnUpdate', $this->model(false, '1')));
    }

    /**
     * A user who may update projects may unlock another user's doc.
     *
     * @return void
     */
    public function testProjectAdminMayUnlock()
    {
        $behavior = $this->behavior('admin-1', true);

        $this->assertTrue($behavior->notify('beforeValidationOnUpdate', $this->model(true, false)));
    }

    /**
     * Another editor cannot change the lock.
     *
     * @return void
     */
    public function testOtherEditorCannotChangeTheLock()
    {
        $behavior = $this->behavior('editor-1', false);
        $model = $this->model('t', '0');
        $model->expects($this->once())->method('appendMessage');

        $this->assertFalse($behavior->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * Making oneself the owner in the same save does not let an editor change
     * the lock: the stored owner counts.
     *
     * @return void
     */
    public function testTheStoredOwnerCounts()
    {
        $behavior = $this->behavior('editor-1', false);
        $model = $this->model(false, true);
        $model->ownerId = 'editor-1';

        $this->assertFalse($behavior->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * Another editor cannot take the doc over, so it cannot change the lock
     * in a later save either.
     *
     * @return void
     */
    public function testOtherEditorCannotChangeTheOwner()
    {
        $behavior = $this->behavior('editor-1', false);
        $model = $this->model(false, false);
        $model->ownerId = 'editor-1';
        $model->expects($this->once())->method('appendMessage');

        $this->assertFalse($behavior->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * The owner, or a user who may update projects, may hand the doc over.
     *
     * @return void
     */
    public function testOwnerOrProjectAdminMayChangeTheOwner()
    {
        $model = $this->model(false, false);
        $model->ownerId = 'editor-1';

        $this->assertTrue($this->behavior('owner-1', false)->notify('beforeValidationOnUpdate', $model));
        $this->assertTrue($this->behavior('admin-1', true)->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * Other changes, or the same lock in another spelling, are not checked.
     *
     * @return void
     */
    public function testAnUnchangedLockIsNotChecked()
    {
        $behavior = $this->behavior('editor-1', false);

        $this->assertTrue($behavior->notify('beforeValidationOnUpdate', $this->model(true, 'true')));
        $this->assertTrue($behavior->notify('beforeValidationOnUpdate', $this->model('f', 0)));
    }

    /**
     * @param  string $userId
     * @param  bool   $mayUpdateProjects
     * @return docBehavior
     */
    private function behavior($userId, $mayUpdateProjects)
    {
        $behavior = $this->getMockBuilder(docBehavior::class)
            ->setMethods(['currentUserId', 'mayUpdateProjects'])
            ->getMock();
        $behavior->method('currentUserId')->willReturn($userId);
        $behavior->method('mayUpdateProjects')->willReturn($mayUpdateProjects);
        return $behavior;
    }

    /**
     * @param  mixed $storedLocked
     * @param  mixed $locked
     * @return ModelInterface
     */
    private function model($storedLocked, $locked)
    {
        $model = $this->getMockBuilder(ModelInterface::class)
            ->addMethods(['getSnapshotData'])
            ->getMockForAbstractClass();
        $model->ownerId = 'owner-1';
        $model->locked = $locked;
        $model->method('getSnapshotData')->willReturn(['ownerId' => 'owner-1', 'locked' => $storedLocked]);
        return $model;
    }
}
