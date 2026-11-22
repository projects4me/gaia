<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Issues\IssueGraph;
use Gaia\Libraries\Issues\ParentEdgeSource;
use Gaia\MVC\Models\Behaviors\issueParentCycleBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for issueParentCycleBehavior over a fake graph. The walk itself
 * is covered by IssueGraphTest, the 422 on the API by the api-tester cases.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueParentCycleBehaviorTest extends TestCase
{
    /**
     * An issue without a parent is not checked.
     *
     * @return void
     */
    public function testAnIssueWithoutParentPasses()
    {
        $graph = $this->graph(IssueGraph::CYCLE);
        $model = ParentFakeModel::make(['id' => 'i1', 'parentId' => null]);

        $this->assertTrue($this->notify('beforeValidationOnCreate', $model, $graph));
        $this->assertSame([], $graph->calls);
    }

    /**
     * An issue cannot be its own parent.
     *
     * @return void
     */
    public function testSelfParentIsRefused()
    {
        $model = ParentFakeModel::make(['id' => 'i1', 'parentId' => 'i1'], ['id' => 'i1', 'parentId' => null]);

        $this->assertFalse($this->notify('beforeValidationOnUpdate', $model, $this->graph(IssueGraph::ACYCLIC)));
        $this->assertSame([issueParentCycleBehavior::PARENT_IS_SELF], $model->texts);
        $this->assertSame(['parentId'], $model->messages);
    }

    /**
     * A descendant as parent is refused, after the graph lock is taken.
     *
     * @return void
     */
    public function testDescendantParentIsRefused()
    {
        $graph = $this->graph(IssueGraph::CYCLE);
        $model = ParentFakeModel::make(['id' => 'i1', 'parentId' => 'i9'], ['id' => 'i1', 'parentId' => 'i2']);

        $this->assertFalse($this->notify('beforeValidationOnUpdate', $model, $graph));
        $this->assertSame(['lock', 'check i1 i9'], $graph->calls);
        $this->assertSame([issueParentCycleBehavior::PARENT_IS_DESCENDANT], $model->texts);
    }

    /**
     * A hierarchy deeper than the cap is refused.
     *
     * @return void
     */
    public function testTooDeepIsRefused()
    {
        $model = ParentFakeModel::make(['id' => 'i1', 'parentId' => 'i9']);

        $this->assertFalse($this->notify('beforeValidationOnCreate', $model, $this->graph(IssueGraph::TOO_DEEP)));
        $this->assertSame([issueParentCycleBehavior::PARENT_TOO_DEEP], $model->texts);
    }

    /**
     * A valid parent passes.
     *
     * @return void
     */
    public function testValidParentPasses()
    {
        $model = ParentFakeModel::make(['id' => 'i1', 'parentId' => 'i9']);

        $this->assertTrue($this->notify('beforeValidationOnCreate', $model, $this->graph(IssueGraph::ACYCLIC)));
        $this->assertSame([], $model->messages);
    }

    /**
     * An update that keeps the parent is not checked.
     *
     * @return void
     */
    public function testAnUpdateKeepingTheParentIsNotChecked()
    {
        $graph = $this->graph(IssueGraph::CYCLE);
        $model = ParentFakeModel::make(['id' => 'i1', 'parentId' => 'i9'], ['id' => 'i1', 'parentId' => 'i9']);

        $this->assertTrue($this->notify('beforeValidationOnUpdate', $model, $graph));
        $this->assertSame([], $graph->calls);
    }

    /**
     * @param  string           $event
     * @param  ParentFakeModel  $model
     * @param  IssueGraph       $graph
     * @return mixed
     */
    private function notify($event, $model, $graph)
    {
        return (new issueParentCycleBehavior([], $graph))->notify($event, $model);
    }

    /**
     * @param  string $result What check() answers
     * @return IssueGraph
     */
    private function graph($result)
    {
        return new class ($result) extends IssueGraph {
            public $calls = [];

            private $result;

            public function __construct($result)
            {
                parent::__construct(new ParentEdgeSource(), 10, new \stdClass());
                $this->result = $result;
            }

            public function lock()
            {
                $this->calls[] = 'lock';
            }

            public function check($from, $to)
            {
                $this->calls[] = 'check ' . $from . ' ' . $to;
                return $this->result;
            }
        };
    }
}

/**
 * The fake model, keeping the message texts too.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ParentFakeModel extends \Phalcon\Mvc\Model
{
    public $id;

    public $parentId;

    /**
     * The fields of the appended messages.
     *
     * @var string[]
     */
    public $messages = [];

    /**
     * The texts of the appended messages.
     *
     * @var string[]
     */
    public $texts = [];

    /**
     * @var array
     */
    private $fakeSnapshot = [];

    /**
     * A model without the ORM's constructor (no DI or metadata needed).
     *
     * @param  array $attributes
     * @param  array $snapshot
     * @return self
     */
    public static function make(array $attributes, array $snapshot = [])
    {
        $model = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        foreach ($attributes as $name => $value) {
            $model->$name = $value;
        }
        $model->fakeSnapshot = $snapshot;
        return $model;
    }

    /**
     * @param  string $attribute
     * @return mixed
     */
    public function readAttribute(string $attribute)
    {
        return $this->$attribute;
    }

    /**
     * @param  \Phalcon\Messages\MessageInterface $message
     * @return \Phalcon\Mvc\ModelInterface
     */
    public function appendMessage(\Phalcon\Messages\MessageInterface $message): \Phalcon\Mvc\ModelInterface
    {
        $this->messages[] = $message->getField();
        $this->texts[] = $message->getMessage();
        return $this;
    }

    /**
     * @return array
     */
    public function getSnapshotData(): array
    {
        return $this->fakeSnapshot;
    }
}
