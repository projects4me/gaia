<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Search\SemanticText;
use Gaia\MVC\Models\Behaviors\searchIndexBehavior;
use Gaia\MVC\REST\Controllers\Components\Events\SearchLiveEvents;
use Gaia\MVC\REST\Controllers\Components\Events\Support\EventNames;
use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;
use Phalcon\Mvc\ModelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for searchIndexBehavior: a create and a semantic edit store the text and hash and publish
 * search.semantic.changed through a fake publisher; other edits do neither, and a Hermes failure never fails the save.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchIndexBehaviorTest extends TestCase
{
    /**
     * The envelopes the fake publisher received.
     *
     * @var array
     */
    private $posted = [];

    /**
     * @var searchIndexBehavior
     */
    private $behavior;

    protected function setUp(): void
    {
        $this->posted = [];
        $this->behavior = new searchIndexBehavior(new SearchLiveEvents(new HermesPublisher(function ($envelope) {
            $this->posted[] = $envelope;
        })));
    }

    /**
     * A create stores the text and hash before the insert and publishes one event after it.
     *
     * @return void
     */
    public function testCreateSetsTheTextAndHashAndPublishes()
    {
        $model = $this->model(['id' => 'i-1', 'subject' => 'Login fails', 'description' => '<p>On <b>Safari</b></p>']);

        $this->save($model, 'Create');

        $this->assertSame("Login fails\nOn Safari", $model->searchSemanticText);
        $this->assertSame(SemanticText::hashOf("Login fails\nOn Safari"), $model->searchSemanticHash);
        $this->assertCount(1, $this->posted);
        $this->assertSame(EventNames::SEARCH_SEMANTIC_CHANGED, $this->posted[0]['eventName']);
        $this->assertSame('service:ares', $this->posted[0]['projectId']);
        $this->assertSame(['type' => 'issue', 'id' => 'i-1'], $this->posted[0]['resource']);
        $this->assertSame(['type' => 'issue', 'resource' => 'issue', 'recordId' => 'i-1'], $this->posted[0]['changes']);
    }

    /**
     * An edit of a semantic field updates the text and hash and publishes.
     *
     * @return void
     */
    public function testASemanticEditUpdatesAndPublishes()
    {
        $model = $this->stored(['subject' => 'Old subject', 'description' => 'Body']);
        $model->subject = 'New subject';

        $this->save($model, 'Update');

        $this->assertSame("New subject\nBody", $model->searchSemanticText);
        $this->assertSame(SemanticText::hashOf("New subject\nBody"), $model->searchSemanticHash);
        $this->assertCount(1, $this->posted);
        $this->assertSame('i-1', $this->posted[0]['changes']['recordId']);
    }

    /**
     * An edit of another field leaves the text and hash alone and publishes nothing, even when they are missing
     * (the reindex backfills them).
     *
     * @return void
     */
    public function testANonSemanticEditDoesNeither()
    {
        $model = $this->stored(['subject' => 'Same', 'description' => 'Body', 'status' => 'new']);
        $model->searchSemanticText = null;
        $model->searchSemanticHash = null;
        $model->snapshot = $this->valuesOf($model);
        $model->status = 'done';

        $this->save($model, 'Update');

        $this->assertNull($model->searchSemanticText);
        $this->assertNull($model->searchSemanticHash);
        $this->assertSame([], $this->posted);
    }

    /**
     * An edit that leaves the semantic text the same (here only the markup changed) publishes nothing.
     *
     * @return void
     */
    public function testAnEditThatKeepsTheTextDoesNotPublish()
    {
        $model = $this->stored(['subject' => 'Same', 'description' => 'Body']);
        $model->description = '<p>Body</p>';

        $this->save($model, 'Update');

        $this->assertSame("Same\nBody", $model->searchSemanticText);
        $this->assertSame([], $this->posted);
    }

    /**
     * A model loaded without a semantic field cannot build the text: the edit clears it for the reindex and publishes
     * nothing.
     *
     * @return void
     */
    public function testAnEditOfAPartlyLoadedModelClearsTheText()
    {
        $model = $this->stored(['subject' => 'Old', 'description' => 'Body']);
        $model->snapshot = array_diff_key($model->snapshot, ['description' => true]);
        unset($model->description);
        $model->subject = 'New';

        $this->save($model, 'Update');

        $this->assertNull($model->searchSemanticText);
        $this->assertNull($model->searchSemanticHash);
        $this->assertSame([], $this->posted);
    }

    /**
     * A semantic field holding a value that is not text counts as changed, and does not fail the save.
     *
     * @return void
     */
    public function testANonScalarValueDoesNotFailTheSave()
    {
        $model = $this->stored(['subject' => 'Same', 'description' => 'Body']);
        $model->description = ['not', 'text'];

        $this->save($model, 'Update');

        $this->assertSame('Same', $model->searchSemanticText);
        $this->assertCount(1, $this->posted);
    }

    /**
     * A Hermes failure is swallowed: the save goes on and the text is stored.
     *
     * @return void
     */
    public function testAPublishFailureDoesNotBreakTheSave()
    {
        $behavior = new searchIndexBehavior(new SearchLiveEvents(new HermesPublisher(function () {
            throw new \RuntimeException('Hermes down');
        })));
        $model = $this->model(['id' => 'i-1', 'subject' => 'Subject']);

        $this->assertNull($behavior->notify('beforeCreate', $model));
        $this->assertNull($behavior->notify('afterCreate', $model));
        $this->assertSame('Subject', $model->searchSemanticText);

        $failing = $this->createMock(SearchLiveEvents::class);
        $failing->method('publishSemanticChanged')->willThrowException(new \RuntimeException('boom'));
        $this->assertNull((new searchIndexBehavior($failing))->notify('afterCreate', $model));
    }

    /**
     * A model without semantic fields, and the other events, are left alone.
     *
     * @return void
     */
    public function testNothingWithoutSemanticFieldsOrForOtherEvents()
    {
        $plain = $this->model(['id' => 'i-1', 'subject' => 'Subject'], ['type' => 'issue', 'semantic' => []]);
        $this->behavior->notify('beforeCreate', $plain);
        $this->behavior->notify('afterCreate', $plain);
        $this->assertFalse(isset($plain->searchSemanticHash));

        $model = $this->model(['id' => 'i-1', 'subject' => 'Subject']);
        $this->behavior->notify('beforeValidationOnCreate', $model);
        $this->behavior->notify('afterDelete', $model);
        $this->assertFalse(isset($model->searchSemanticHash));
        $this->assertSame([], $this->posted);
    }

    /**
     * Runs the behaviour's before and after events of a save, refreshing the snapshot in between as Phalcon does.
     *
     * @param  ModelInterface $model
     * @param  string         $operation Create or Update
     * @return void
     */
    private function save($model, $operation)
    {
        $this->behavior->notify('before' . $operation, $model);
        $model->old = $model->snapshot;
        $model->snapshot = $this->valuesOf($model);
        $this->behavior->notify('after' . $operation, $model);
    }

    /**
     * A loaded Issue: its values are its snapshot, with the text and hash the behaviour stored.
     *
     * @param  array $values
     * @return ModelInterface
     */
    private function stored(array $values)
    {
        $model = $this->model(['id' => 'i-1'] + $values);
        $text = SemanticText::forBlock(['semantic' => ['subject', 'description']])->text($values);
        $model->searchSemanticText = $text;
        $model->searchSemanticHash = SemanticText::hashOf($text);
        $model->snapshot = $this->valuesOf($model);

        return $model;
    }

    /**
     * An Issue model mock with a search block; snapshot and old hold its snapshots.
     *
     * @param  array      $values
     * @param  array|null $block
     * @return ModelInterface
     */
    private function model(array $values, ?array $block = null)
    {
        $model = $this->getMockBuilder(ModelInterface::class)
            ->addMethods(['getMetaData', 'getSnapshotData', 'getOldSnapshotData', 'getModelName'])
            ->getMockForAbstractClass();
        $model->method('getMetaData')->willReturn([
            'search' => $block ?: ['type' => 'issue', 'semantic' => ['subject', 'description']],
        ]);
        $model->method('getModelName')->willReturn('Issue');
        $model->method('getSnapshotData')->willReturnCallback(function () use ($model) {
            return $model->snapshot;
        });
        $model->method('getOldSnapshotData')->willReturnCallback(function () use ($model) {
            return $model->old;
        });
        $model->snapshot = [];
        $model->old = [];
        foreach ($values as $field => $value) {
            $model->{$field} = $value;
        }

        return $model;
    }

    /**
     * The model's field values, without the mock's snapshot holders.
     *
     * @param  ModelInterface $model
     * @return array
     */
    private function valuesOf($model)
    {
        $values = [];
        foreach (['id', 'subject', 'description', 'status', 'searchSemanticText', 'searchSemanticHash'] as $field) {
            if (isset($model->{$field})) {
                $values[$field] = $model->{$field};
            }
        }

        return $values;
    }
}
