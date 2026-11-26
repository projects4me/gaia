<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Tags\TagName;
use Gaia\MVC\Models\Behaviors\tagBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for tagBehavior over in-memory tags. The queries are covered by
 * the api-tester cases.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TagBehaviorTest extends TestCase
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
            ['id' => 't2', 'projectId' => 'p1', 'tag' => 'Frontend'],
        ];
    }

    public function testCreateStoresTheNormalisedName()
    {
        $model = TagFakeModel::make(['tag' => "  Needs \t  Review ", 'projectId' => 'p1']);

        $this->assertTrue($this->notify('beforeValidationOnCreate', $model));
        $this->assertSame('Needs Review', $model->tag);
    }

    public function testCreateRefusesANameTakenInTheProjectWhateverItsCase()
    {
        $model = TagFakeModel::make(['tag' => 'backend ', 'projectId' => 'p1']);

        $this->assertFalse($this->notify('beforeValidationOnCreate', $model));
        $this->assertSame([tagBehavior::DUPLICATE], $model->texts);
        $this->assertSame(['tag'], $model->messages);
    }

    public function testCreateAllowsTheSameNameInAnotherProject()
    {
        $model = TagFakeModel::make(['tag' => 'Backend', 'projectId' => 'p2']);

        $this->assertTrue($this->notify('beforeValidationOnCreate', $model));
    }

    public function testCreateNeedsAProject()
    {
        $model = TagFakeModel::make(['tag' => 'Ops', 'projectId' => null]);

        $this->assertFalse($this->notify('beforeValidationOnCreate', $model));
        $this->assertSame([tagBehavior::NEEDS_PROJECT], $model->texts);
        $this->assertSame(['projectId'], $model->messages);
    }

    public function testCreateRefusesAnEmptyOrTooLongName()
    {
        foreach (['   ' => TagName::EMPTY_NAME, str_repeat('a', 51) => TagName::TOO_LONG] as $name => $error) {
            $model = TagFakeModel::make(['tag' => $name, 'projectId' => 'p1']);

            $this->assertFalse($this->notify('beforeValidationOnCreate', $model), $error);
            $this->assertSame([$error], $model->texts);
            $this->assertSame(['tag'], $model->messages);
        }
    }

    public function testUpdateMayKeepItsOwnNameButNotTakeAnother()
    {
        $stored = ['id' => 't1', 'tag' => 'Backend', 'projectId' => 'p1'];

        $model = TagFakeModel::make(array_merge($stored, ['tag' => 'BACKEND']), $stored);
        $this->assertTrue($this->notify('beforeValidationOnUpdate', $model));
        $this->assertSame('BACKEND', $model->tag);

        $model = TagFakeModel::make(array_merge($stored, ['tag' => 'frontend']), $stored);
        $this->assertFalse($this->notify('beforeValidationOnUpdate', $model));
        $this->assertSame([tagBehavior::DUPLICATE], $model->texts);
    }

    public function testUpdateCannotMoveTheTagToAnotherProject()
    {
        $stored = ['id' => 't1', 'tag' => 'Backend', 'projectId' => 'p1'];
        $model = TagFakeModel::make(array_merge($stored, ['projectId' => 'p2']), $stored);

        $this->assertFalse($this->notify('beforeValidationOnUpdate', $model));
        $this->assertSame([tagBehavior::PROJECT_FIXED], $model->texts);
        $this->assertSame(['projectId'], $model->messages);
    }

    /**
     * @param  string       $event
     * @param  TagFakeModel $model
     * @return mixed
     */
    private function notify($event, $model)
    {
        return (new tagBehavior([], $this->tags))->notify($event, $model);
    }
}
