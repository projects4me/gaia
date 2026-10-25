<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\Models\Behaviors\ideaReferencesBehavior;
use Phalcon\Mvc\ModelInterface;

/**
 * Unit tests for ideaReferencesBehavior: the plan of an idea record must be a
 * plan of the same idea, and a linked issue must exist.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IdeaReferencesBehaviorTest extends TestCase
{
    /**
     * A plan of the same idea and an existing issue pass.
     *
     * @return void
     */
    public function testCreateAcceptsAPlanOfTheSameIdeaAndAnExistingIssue()
    {
        $behavior = $this->behavior(true, true);
        $model = $this->model(['ideaId' => 'idea-1', 'ideaplanId' => 'plan-1', 'issueId' => 'issue-1']);
        $model->expects($this->never())->method('appendMessage');

        $this->assertNotFalse($behavior->notify('beforeValidationOnCreate', $model));
    }

    /**
     * A plan of another idea is rejected on ideaplanId.
     *
     * @return void
     */
    public function testCreateRejectsAPlanOfAnotherIdea()
    {
        $behavior = $this->behavior(false, true);
        $model = $this->model(['ideaId' => 'idea-1', 'ideaplanId' => 'plan-of-idea-2', 'issueId' => 'issue-1']);
        $model->expects($this->once())->method('appendMessage')
            ->with($this->callback(function ($message) {
                return $message->getField() === 'ideaplanId';
            }));

        $this->assertFalse($behavior->notify('beforeValidationOnCreate', $model));
    }

    /**
     * An issueId that names no issue is rejected on issueId.
     *
     * @return void
     */
    public function testCreateRejectsAMissingIssue()
    {
        $behavior = $this->behavior(true, false);
        $model = $this->model(['ideaId' => 'idea-1', 'ideaplanId' => 'plan-1', 'issueId' => 'nope']);
        $model->expects($this->once())->method('appendMessage')
            ->with($this->callback(function ($message) {
                return $message->getField() === 'issueId';
            }));

        $this->assertFalse($behavior->notify('beforeValidationOnCreate', $model));
    }

    /**
     * A message without a plan or an issue has nothing to check.
     *
     * @return void
     */
    public function testCreateSkipsEmptyReferences()
    {
        $behavior = $this->getMockBuilder(ideaReferencesBehavior::class)
            ->setMethods(['planBelongsToIdea', 'issueExists'])
            ->getMock();
        $behavior->expects($this->never())->method('planBelongsToIdea');
        $behavior->expects($this->never())->method('issueExists');
        $model = $this->model(['ideaId' => 'idea-1', 'ideaplanId' => null]);

        $this->assertNotFalse($behavior->notify('beforeValidationOnCreate', $model));
    }

    /**
     * A plan issue must name its plan and its issue: an empty value is
     * rejected when the field is declared not null.
     *
     * @return void
     */
    public function testCreateRejectsEmptyRequiredReferences()
    {
        $behavior = $this->behavior(true, true);
        $model = $this->model(
            ['ideaId' => 'idea-1', 'ideaplanId' => '', 'issueId' => 'issue-1'],
            [],
            ['ideaplanId' => ['null' => false], 'issueId' => ['null' => false]]
        );
        $model->expects($this->once())->method('appendMessage')
            ->with($this->callback(function ($message) {
                return $message->getField() === 'ideaplanId';
            }));

        $this->assertFalse($behavior->notify('beforeValidationOnCreate', $model));
    }

    /**
     * An empty required issueId is rejected on issueId.
     *
     * @return void
     */
    public function testCreateRejectsAnEmptyRequiredIssue()
    {
        $behavior = $this->behavior(true, true);
        $model = $this->model(
            ['ideaId' => 'idea-1', 'ideaplanId' => 'plan-1', 'issueId' => ''],
            [],
            ['ideaplanId' => ['null' => false], 'issueId' => ['null' => false]]
        );
        $model->expects($this->once())->method('appendMessage')
            ->with($this->callback(function ($message) {
                return $message->getField() === 'issueId';
            }));

        $this->assertFalse($behavior->notify('beforeValidationOnCreate', $model));
    }

    /**
     * An update that keeps the references does not check them again.
     *
     * @return void
     */
    public function testUpdateSkipsUnchangedReferences()
    {
        $behavior = $this->getMockBuilder(ideaReferencesBehavior::class)
            ->setMethods(['planBelongsToIdea', 'issueExists'])
            ->getMock();
        $behavior->expects($this->never())->method('planBelongsToIdea');
        $behavior->expects($this->never())->method('issueExists');
        $values = ['ideaId' => 'idea-1', 'ideaplanId' => 'plan-1', 'issueId' => 'issue-1'];
        $model = $this->model($values, $values);

        $this->assertNotFalse($behavior->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * Moving a record to another plan checks that plan.
     *
     * @return void
     */
    public function testUpdateRejectsMovingToAPlanOfAnotherIdea()
    {
        $behavior = $this->behavior(false, true);
        $model = $this->model(
            ['ideaId' => 'idea-1', 'ideaplanId' => 'plan-of-idea-2', 'issueId' => 'issue-1'],
            ['ideaId' => 'idea-1', 'ideaplanId' => 'plan-1', 'issueId' => 'issue-1']
        );

        $this->assertFalse($behavior->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * @param  bool $planBelongs
     * @param  bool $issueExists
     * @return ideaReferencesBehavior
     */
    private function behavior($planBelongs, $issueExists)
    {
        $behavior = $this->getMockBuilder(ideaReferencesBehavior::class)
            ->setMethods(['planBelongsToIdea', 'issueExists'])
            ->getMock();
        $behavior->method('planBelongsToIdea')->willReturn($planBelongs);
        $behavior->method('issueExists')->willReturn($issueExists);

        return $behavior;
    }

    /**
     * @param  array $values
     * @param  array $snapshot
     * @param  array $fields   field metadata, e.g. ['issueId' => ['null' => false]]
     * @return ModelInterface
     */
    private function model(array $values, array $snapshot = [], array $fields = [])
    {
        $model = $this->getMockBuilder(ModelInterface::class)
            ->addMethods(['getSnapshotData', 'getMetaData'])
            ->getMockForAbstractClass();
        $model->method('getSnapshotData')->willReturn($snapshot);
        $model->method('getMetaData')->willReturn(['fields' => $fields]);
        foreach ($values as $field => $value) {
            $model->{$field} = $value;
        }

        return $model;
    }
}
