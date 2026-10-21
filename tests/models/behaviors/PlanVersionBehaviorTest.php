<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use PHPUnit\Framework\TestCase;
use Gaia\MVC\Models\Behaviors\planVersionBehavior;
use Phalcon\Mvc\ModelInterface;

/**
 * Unit tests for planVersionBehavior: plan versions number 1, 2, 3 per idea.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PlanVersionBehaviorTest extends TestCase
{
    /**
     * The first plan of an idea is version 1.
     *
     * @return void
     */
    public function testFirstPlanIsVersionOne()
    {
        $behavior = new planVersionBehavior();

        $this->assertSame(1, $behavior->nextVersion(null));
    }

    /**
     * Later plans follow the latest version (the database returns it as a string).
     *
     * @return void
     */
    public function testNextVersionFollowsTheLatest()
    {
        $behavior = new planVersionBehavior();

        $this->assertSame(2, $behavior->nextVersion(1));
        $this->assertSame(3, $behavior->nextVersion('2'));
    }

    /**
     * Creating a plan overwrites whatever version the request sent.
     *
     * @return void
     */
    public function testCreateAssignsTheIdeasNextVersion()
    {
        $behavior = $this->getMockBuilder(planVersionBehavior::class)
            ->setMethods(['getLatestVersion'])
            ->getMock();
        $behavior->method('getLatestVersion')->willReturn('2');

        $model = $this->createMock(ModelInterface::class);
        $model->version = 99;

        $behavior->notify('beforeValidationOnCreate', $model);

        $this->assertSame(3, $model->version);
    }
}
