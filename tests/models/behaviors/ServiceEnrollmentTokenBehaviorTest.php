<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Authorization\ServiceClientScopes;
use Gaia\MVC\Models\Behaviors\serviceEnrollmentTokenBehavior;

/**
 * Unit tests for serviceEnrollmentTokenBehavior: a new token is unused and has
 * a name and a configured kind; its own fields never change and it is spent
 * only once.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceEnrollmentTokenBehaviorTest extends TestCase
{
    public function testANewUnusedTokenOfAKnownKindIsAccepted()
    {
        $model = ServiceEnrollmentTokenFakeModel::make(['name' => 'Deploy', 'kind' => 'runtime']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame([], $model->messages);
    }

    public function testANewTokenNeedsANameAKnownKindAndNoUse()
    {
        $cases = [
            'name' => ['name' => '  ', 'kind' => 'runtime'],
            'kind' => ['name' => 'Deploy', 'kind' => 'mailer'],
            'usedAt' => ['name' => 'Deploy', 'kind' => 'runtime', 'usedAt' => '2027-01-01 00:00:00'],
        ];
        foreach ($cases as $field => $attributes) {
            $model = ServiceEnrollmentTokenFakeModel::make($attributes);

            $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model), $field);
            $this->assertSame([$field], $model->messages, $field);
        }
    }

    public function testTheFieldsSetOnCreationNeverChange()
    {
        foreach (serviceEnrollmentTokenBehavior::FIXED_FIELDS as $field) {
            $model = $this->stored([$field => 'changed']);

            $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model), $field);
            $this->assertSame([$field], $model->messages, $field);
        }
    }

    public function testAnUnusedTokenCanBeSpent()
    {
        $model = $this->stored(['usedAt' => '2027-01-01 00:00:00']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame([], $model->messages);
    }

    public function testASpentTokenCannotBeSpentAgain()
    {
        $model = $this->stored(['usedAt' => '2027-02-02 00:00:00'], '2027-01-01 00:00:00');

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['usedAt'], $model->messages);
    }

    /**
     * A stored token with the given changes applied on top of its snapshot.
     *
     * @param  array       $changes
     * @param  string|null $usedAt  The stored usedAt
     * @return ServiceEnrollmentTokenFakeModel
     */
    private function stored(array $changes, $usedAt = null)
    {
        $snapshot = [
            'name' => 'Deploy',
            'kind' => 'runtime',
            'tokenHash' => 'hash',
            'expiresAt' => '2027-01-01 00:15:00',
            'usedAt' => $usedAt,
        ];

        return ServiceEnrollmentTokenFakeModel::make($changes + $snapshot, $snapshot);
    }

    /**
     * @return serviceEnrollmentTokenBehavior
     */
    private function behavior()
    {
        return new class extends serviceEnrollmentTokenBehavior {
            protected function scopes()
            {
                return new ServiceClientScopes(['kinds' => [
                    'runtime' => ['scopes' => ['agent.read']],
                ]]);
            }
        };
    }
}

/**
 * A model stand-in that keeps attributes, messages and a snapshot in memory.
 */
class ServiceEnrollmentTokenFakeModel extends \Phalcon\Mvc\Model
{
    public $name;
    public $kind;
    public $tokenHash;
    public $expiresAt;
    public $usedAt;

    public $messages = [];

    private $fakeSnapshot;

    public static function make(array $attributes, array $snapshot = [])
    {
        $model = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        foreach ($attributes as $name => $value) {
            $model->$name = $value;
        }
        $model->fakeSnapshot = $snapshot;
        return $model;
    }

    public function readAttribute(string $attribute)
    {
        return $this->$attribute;
    }

    public function appendMessage(\Phalcon\Messages\MessageInterface $message): \Phalcon\Mvc\ModelInterface
    {
        $this->messages[] = $message->getField();
        return $this;
    }

    public function getSnapshotData(): array
    {
        return $this->fakeSnapshot;
    }
}
