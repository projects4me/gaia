<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Controller\Components;

use Gaia\MVC\REST\Controllers\Components\ProjectActivitiesComponent;
use Gaia\Tests\Models\Behaviors\UnsetAttributeModelStub;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProjectActivitiesComponent: activities of a project's related
 * records are linked to the project through a field the record has, and the
 * membership activity reads only fields a membership has.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ProjectActivitiesComponentTest extends TestCase
{
    /**
     * Each related record's relatedKey is a field of its model, so the
     * activity is linked to the project instead of to nothing.
     *
     * @return void
     */
    public function testRelatedKeysAreFieldsOfTheirModels()
    {
        $property = new \ReflectionProperty(ProjectActivitiesComponent::class, 'activityMeta');
        gaiaTestMakeAccessible($property);
        $activityMeta = $property->getValue(new ProjectActivitiesComponent());

        $checked = 0;
        foreach ($activityMeta as $controllerName => $config) {
            if ($config['type'] !== 'rel') {
                continue;
            }
            $modelName = ucfirst($controllerName);
            $fields = $this->modelFields($modelName);
            $this->assertArrayHasKey($config['relatedKey'], $fields, "$modelName has no field {$config['relatedKey']}");
            $checked++;
        }
        $this->assertGreaterThan(0, $checked);
    }

    /**
     * The membership activity names the member; a membership has no role, so
     * the message names none.
     *
     * @return void
     */
    public function testMembershipCreatedActivityNamesTheMember()
    {
        $component = $this->getMockBuilder(ProjectActivitiesComponent::class)
            ->onlyMethods(['findUserName'])
            ->getMock();
        $component->expects($this->once())->method('findUserName')->with('user-1')->willReturn('Jane');
        $model = $this->getMockBuilder(UnsetAttributeModelStub::class)->getMockForAbstractClass();
        $model->userId = 'user-1';
        $model->projectId = 'project-1';

        $method = new \ReflectionMethod(ProjectActivitiesComponent::class, 'membershipCreatedActivity');
        gaiaTestMakeAccessible($method);

        $this->assertSame('New member <b>Jane</b> has been added to the project', $method->invoke($component, $model));
    }

    /**
     * The fields of a model, from its metadata.
     *
     * @param  string $modelName
     * @return array
     */
    private function modelFields($modelName)
    {
        $models = [];
        $models = include APP_PATH . '/app/metadata/model/' . $modelName . '.php';
        return $models[$modelName]['fields'];
    }
}
