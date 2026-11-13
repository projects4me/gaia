<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\Models\Behaviors\doccontentBehavior;
use Phalcon\Mvc\ModelInterface;

/**
 * Unit tests for doccontentBehavior: the doc must be of the content's project,
 * a doc has one content row, and byteSize follows the state.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DoccontentBehaviorTest extends TestCase
{
    /**
     * A valid new content row is accepted and its byteSize is the state length,
     * whatever the request sent.
     *
     * @return void
     */
    public function testCreateAcceptsAValidRowAndSetsByteSize()
    {
        $behavior = $this->behavior(true, true);
        $model = $this->model('SGVsbG8=');
        $model->byteSize = 999;

        $this->assertTrue($behavior->notify('beforeValidationOnCreate', $model));
        $this->assertSame(8, $model->byteSize);
    }

    /**
     * A doc outside the project cancels the save.
     *
     * @return void
     */
    public function testCreateRejectsADocOfAnotherProject()
    {
        $behavior = $this->behavior(false, true);

        $this->assertFalse($behavior->notify('beforeValidationOnCreate', $this->model('x')));
    }

    /**
     * A second content row for the doc cancels the save.
     *
     * @return void
     */
    public function testCreateRejectsASecondRowForTheDoc()
    {
        $behavior = $this->behavior(true, false);

        $this->assertFalse($behavior->notify('beforeValidationOnCreate', $this->model('x')));
    }

    /**
     * Unchanged doc and project are not checked again on update, but the
     * byteSize follows the new state.
     *
     * @return void
     */
    public function testUpdateSkipsUnchangedDocAndKeepsByteSizeInStep()
    {
        $behavior = $this->behavior(false, false);
        $model = $this->model('abcd');
        $model->method('getSnapshotData')->willReturn(['docId' => 'doc-1', 'projectId' => 'project-1']);

        $this->assertTrue($behavior->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(4, $model->byteSize);
    }

    /**
     * A changed docId is checked on update.
     *
     * @return void
     */
    public function testUpdateChecksAChangedDoc()
    {
        $behavior = $this->behavior(false, true);
        $model = $this->model('x');
        $model->method('getSnapshotData')->willReturn(['docId' => 'doc-0', 'projectId' => 'project-1']);

        $this->assertFalse($behavior->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * A state that is not a string cancels the save.
     *
     * @return void
     */
    public function testCreateRejectsAStateThatIsNotAString()
    {
        $behavior = $this->behavior(true, true);
        $model = $this->model(['x']);
        $model->expects($this->once())->method('appendMessage');

        $this->assertFalse($behavior->notify('beforeValidationOnCreate', $model));
    }

    /**
     * @param  bool $docOk
     * @param  bool $uniqueOk
     * @return doccontentBehavior
     */
    private function behavior($docOk, $uniqueOk)
    {
        $behavior = $this->getMockBuilder(doccontentBehavior::class)
            ->setMethods(['checkDoc', 'checkUnique'])
            ->getMock();
        $behavior->method('checkDoc')->willReturn($docOk);
        $behavior->method('checkUnique')->willReturn($uniqueOk);
        return $behavior;
    }

    /**
     * @param  string|null $state
     * @return ModelInterface
     */
    private function model($state)
    {
        $model = $this->getMockBuilder(ModelInterface::class)
            ->addMethods(['getSnapshotData'])
            ->getMockForAbstractClass();
        $model->docId = 'doc-1';
        $model->projectId = 'project-1';
        $model->state = $state;
        return $model;
    }
}
