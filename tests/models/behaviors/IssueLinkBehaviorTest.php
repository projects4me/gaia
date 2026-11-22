<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Issues\IssueGraph;
use Gaia\Libraries\Issues\IssueLinkRules;
use Gaia\Libraries\Issues\IssueLinks;
use Gaia\Libraries\Issues\LinkEdgeSource;
use Gaia\MVC\Models\Behaviors\issueLinkBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for issueLinkBehavior over fake rules. The rules are covered by
 * IssueLinkRulesTest, the API by the api-tester cases.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueLinkBehaviorTest extends TestCase
{
    /**
     * @var string[]
     */
    private $calls = [];

    /**
     * A passing link gets both project ids from the rules, over any sent
     * value, after the lock is taken.
     *
     * @return void
     */
    public function testCreateSetsProjectsFromIssues()
    {
        $model = LinkFakeModel::make([
            'sourceIssueId' => 'a', 'targetIssueId' => 'b', 'type' => 'blocks',
            'projectId' => 'sent', 'targetProjectId' => 'sent',
        ]);

        $this->assertTrue($this->notify('beforeValidationOnCreate', $model, null));
        $this->assertSame('p1', $model->projectId);
        $this->assertSame('p2', $model->targetProjectId);
        $this->assertSame(['lock', 'check a b blocks'], $this->calls);
    }

    /**
     * A broken rule cancels the save with its message.
     *
     * @return void
     */
    public function testCreateRefusesABrokenRule()
    {
        $model = LinkFakeModel::make(['sourceIssueId' => 'a', 'targetIssueId' => 'd', 'type' => 'relates']);

        $this->assertFalse($this->notify('beforeValidationOnCreate', $model, IssueLinkRules::OTHER_WORKSPACE));
        $this->assertSame([IssueLinkRules::OTHER_WORKSPACE], $model->texts);
        $this->assertNull($model->projectId);
    }

    /**
     * A missing value is left to required-field validation.
     *
     * @return void
     */
    public function testCreateWithoutIssuesIsLeftToValidation()
    {
        $model = LinkFakeModel::make(['sourceIssueId' => 'a', 'targetIssueId' => null, 'type' => 'relates']);

        $this->assertTrue($this->notify('beforeValidationOnCreate', $model, null));
        $this->assertSame([], $this->calls);
    }

    /**
     * The type and the issues cannot change.
     *
     * @return void
     */
    public function testUpdateRefusesChangedTypeOrIssues()
    {
        $stored = ['sourceIssueId' => 'a', 'targetIssueId' => 'b', 'type' => 'blocks'];
        foreach (['type' => 'relates', 'sourceIssueId' => 'c', 'targetIssueId' => 'c'] as $field => $value) {
            $model = LinkFakeModel::make(array_merge($stored, [$field => $value]), $stored);

            $this->assertFalse($this->notify('beforeValidationOnUpdate', $model, null), $field);
            $this->assertSame([issueLinkBehavior::IMMUTABLE], $model->texts);
            $this->assertSame([$field], $model->messages);
        }
    }

    /**
     * Other updates keep the stored project ids.
     *
     * @return void
     */
    public function testUpdateKeepsStoredProjects()
    {
        $stored = [
            'sourceIssueId' => 'a', 'targetIssueId' => 'b', 'type' => 'blocks',
            'projectId' => 'p1', 'targetProjectId' => 'p2',
        ];
        $model = LinkFakeModel::make(array_merge($stored, ['projectId' => 'x', 'targetProjectId' => 'y']), $stored);

        $this->assertTrue($this->notify('beforeValidationOnUpdate', $model, null));
        $this->assertSame('p1', $model->projectId);
        $this->assertSame('p2', $model->targetProjectId);
        $this->assertSame([], $this->calls);
    }

    /**
     * @param  string        $event
     * @param  LinkFakeModel $model
     * @param  string|null   $error What the rules answer
     * @return mixed
     */
    private function notify($event, $model, $error)
    {
        $calls = &$this->calls;
        $rules = new class ($error, $calls) extends IssueLinkRules {
            private $error;

            private $calls;

            public function __construct($error, &$calls)
            {
                $this->error = $error;
                $this->calls = &$calls;
            }

            public function check($sourceIssueId, $targetIssueId, $type)
            {
                $this->calls[] = 'check ' . $sourceIssueId . ' ' . $targetIssueId . ' ' . $type;
                return $this->error === null
                    ? ['error' => null, 'projectId' => 'p1', 'targetProjectId' => 'p2']
                    : ['error' => $this->error, 'projectId' => null, 'targetProjectId' => null];
            }
        };
        $graph = new class ($calls) extends IssueGraph {
            private $calls;

            public function __construct(&$calls)
            {
                parent::__construct(new LinkEdgeSource('blocks'), 10, new \stdClass());
                $this->calls = &$calls;
            }

            public function lock()
            {
                $this->calls[] = 'lock';
            }
        };

        return (new issueLinkBehavior([], $rules, $graph))->notify($event, $model);
    }
}

/**
 * The fake link model, keeping the message fields and texts.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LinkFakeModel extends \Phalcon\Mvc\Model
{
    public $sourceIssueId;

    public $targetIssueId;

    public $type;

    public $projectId;

    public $targetProjectId;

    /**
     * @var string[]
     */
    public $messages = [];

    /**
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
     * @param  string $attribute
     * @param  mixed  $value
     * @return void
     */
    public function writeAttribute(string $attribute, $value): void
    {
        $this->$attribute = $value;
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
