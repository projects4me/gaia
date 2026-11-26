<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\MVC\Models\Behaviors\taggedBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for taggedBehavior over in-memory tags and records. The queries
 * are covered by the api-tester cases.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TaggedBehaviorTest extends TestCase
{
    /**
     * @var FakeTags
     */
    private $tags;

    protected function setUp(): void
    {
        $this->tags = new FakeTags();
        $this->tags->tags = [
            ['id' => 't1', 'projectId' => 'p1', 'tag' => 'Backend'],
            ['id' => 't2', 'projectId' => 'p2', 'tag' => 'Backend'],
        ];
        $this->tags->records = ['issue:i1' => 'p1', 'conversationroom:r1' => 'p1'];
        $this->tags->applied = ['t1:issue:i1'];
    }

    public function testCreateTagsARecordOfTheTagsProject()
    {
        $model = TagFakeModel::make(['tagId' => 't1', 'relatedTo' => 'conversationroom', 'relatedId' => 'r1']);

        $this->assertTrue($this->notify('beforeValidationOnCreate', $model));
        $this->assertSame([], $model->texts);
    }

    public function testCreateRefusesATagOfAnotherProject()
    {
        $model = TagFakeModel::make(['tagId' => 't2', 'relatedTo' => 'conversationroom', 'relatedId' => 'r1']);

        $this->assertFalse($this->notify('beforeValidationOnCreate', $model));
        $this->assertSame([taggedBehavior::OTHER_PROJECT], $model->texts);
        $this->assertSame(['tagId'], $model->messages);
    }

    public function testCreateRefusesATagTheRecordAlreadyHas()
    {
        $model = TagFakeModel::make(['tagId' => 't1', 'relatedTo' => 'issue', 'relatedId' => 'i1']);

        $this->assertFalse($this->notify('beforeValidationOnCreate', $model));
        $this->assertSame([taggedBehavior::DUPLICATE], $model->texts);
    }

    public function testCreateRefusesAnUnknownTag()
    {
        $model = TagFakeModel::make(['tagId' => 'nope', 'relatedTo' => 'issue', 'relatedId' => 'i1']);

        $this->assertFalse($this->notify('beforeValidationOnCreate', $model));
        $this->assertSame([taggedBehavior::UNKNOWN_TAG], $model->texts);
    }

    public function testCreateRefusesARecordThatCantBeTagged()
    {
        $model = TagFakeModel::make(['tagId' => 't1', 'relatedTo' => 'project', 'relatedId' => 'p1']);

        $this->assertFalse($this->notify('beforeValidationOnCreate', $model));
        $this->assertSame([taggedBehavior::NOT_TAGGABLE], $model->texts);
        $this->assertSame(['relatedId'], $model->messages);
    }

    public function testCreateWithAMissingValueIsLeftToValidation()
    {
        $model = TagFakeModel::make(['tagId' => null, 'relatedTo' => 'issue', 'relatedId' => 'i1']);

        $this->assertTrue($this->notify('beforeValidationOnCreate', $model));
    }

    public function testUpdateCannotChangeTheTaggingItself()
    {
        $stored = ['tagId' => 't1', 'relatedTo' => 'issue', 'relatedId' => 'i1'];
        foreach (['tagId' => 't2', 'relatedTo' => 'doc', 'relatedId' => 'i2'] as $field => $value) {
            $model = TagFakeModel::make(array_merge($stored, [$field => $value]), $stored);

            $this->assertFalse($this->notify('beforeValidationOnUpdate', $model), $field);
            $this->assertSame([taggedBehavior::IMMUTABLE], $model->texts);
            $this->assertSame([$field], $model->messages);
        }
    }

    /**
     * @param  string       $event
     * @param  TagFakeModel $model
     * @return mixed
     */
    private function notify($event, $model)
    {
        return (new taggedBehavior([], $this->tags))->notify($event, $model);
    }
}
