<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Authorization\ServiceClients;
use Gaia\Libraries\Authorization\ServiceClientScopes;
use Gaia\MVC\Models\Behaviors\serviceClientBehavior;

/**
 * Unit tests for serviceClientBehavior: the kind is a configured kind, a new
 * client gets its kind's default scopes and the active status, scopes are a
 * JSON list of names, and kind and clientId never change.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceClientBehaviorTest extends TestCase
{
    public function testANewClientGetsItsKindsDefaultsAndIsActive()
    {
        $model = ServiceClientFakeModel::make(['kind' => 'runtime', 'clientId' => 'svc-1']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame('["agent.delegate","agent.read"]', $model->scopes);
        $this->assertSame('active', $model->status);
        $this->assertSame([], $model->messages);
    }

    public function testOwnScopesAreKeptAndAListIsStoredAsJson()
    {
        $model = ServiceClientFakeModel::make(['kind' => 'runtime', 'scopes' => ['agent.read'], 'status' => 'disabled']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame('["agent.read"]', $model->scopes);
        $this->assertSame('disabled', $model->status);
    }

    public function testAnUnknownKindIsRefused()
    {
        $model = ServiceClientFakeModel::make(['kind' => 'mailer']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['kind'], $model->messages);
    }

    public function testAnUnknownStatusIsRefused()
    {
        $model = ServiceClientFakeModel::make(['kind' => 'hermes', 'status' => 'paused']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame(['status'], $model->messages);
    }

    public function testScopesMustBeAListOfNames()
    {
        foreach (['{"agent.read":true}', '["agent read"]', '[1]', 'not json'] as $scopes) {
            $model = ServiceClientFakeModel::make(['kind' => 'hermes', 'scopes' => $scopes]);

            $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model), $scopes);
            $this->assertSame(['scopes'], $model->messages, $scopes);
        }
    }

    public function testTheKindCannotChange()
    {
        $model = ServiceClientFakeModel::make(
            ['kind' => 'hermes', 'clientId' => 'svc-1', 'status' => 'active', 'scopes' => '["token.introspect"]'],
            ['kind' => 'runtime', 'clientId' => 'svc-1']
        );

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['kind'], $model->messages);
    }

    public function testTheClientIdCannotChange()
    {
        $model = ServiceClientFakeModel::make(
            ['kind' => 'runtime', 'clientId' => 'svc-2', 'status' => 'active', 'scopes' => '["agent.read"]'],
            ['kind' => 'runtime', 'clientId' => 'svc-1']
        );

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['clientId'], $model->messages);
    }

    public function testAStatusChangePasses()
    {
        $model = ServiceClientFakeModel::make(
            ['kind' => 'runtime', 'clientId' => 'svc-1', 'status' => 'disabled', 'scopes' => '["agent.read"]'],
            ['kind' => 'runtime', 'clientId' => 'svc-1', 'status' => 'active']
        );

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame([], $model->messages);
    }

    public function testDisablingAClientRevokesItsTokens()
    {
        $model = ServiceClientFakeModel::make(
            ['kind' => 'runtime', 'clientId' => 'svc-1', 'status' => 'disabled'],
            ['kind' => 'runtime', 'clientId' => 'svc-1', 'status' => 'active']
        );
        $behavior = $this->behavior();

        $this->assertTrue($behavior->notify('beforeUpdate', $model));
        $this->assertSame(['svc-1'], $behavior->revoked);
    }

    public function testOtherUpdatesLeaveTheTokensAlone()
    {
        $cases = [
            'stays disabled' => [['status' => 'disabled'], ['status' => 'disabled']],
            'enabled' => [['status' => 'active'], ['status' => 'disabled']],
            'stays active' => [['status' => 'active'], ['status' => 'active']],
        ];
        foreach ($cases as $label => [$attributes, $snapshot]) {
            $model = ServiceClientFakeModel::make($attributes + ['clientId' => 'svc-1'], $snapshot);
            $behavior = $this->behavior();

            $this->assertTrue($behavior->notify('beforeUpdate', $model), $label);
            $this->assertSame([], $behavior->revoked, $label);
        }
    }

    /**
     * The behavior with a fixed configuration of kinds, recording the clients
     * whose tokens it revokes.
     *
     * @return serviceClientBehavior
     */
    private function behavior()
    {
        return new class extends serviceClientBehavior {
            /** @var string[] */
            public $revoked = [];

            protected function serviceClients()
            {
                $behavior = $this;
                return new class ($behavior) extends ServiceClients {
                    private $behavior;

                    public function __construct($behavior)
                    {
                        $this->behavior = $behavior;
                    }

                    public function revokeTokens($clientId)
                    {
                        $this->behavior->revoked[] = $clientId;
                    }
                };
            }

            protected function scopes()
            {
                return new ServiceClientScopes([
                    'kinds' => [
                        'runtime' => ['scopes' => ['agent.delegate', 'agent.read']],
                        'hermes' => ['scopes' => ['token.introspect']],
                    ],
                ]);
            }
        };
    }
}

/**
 * A model stand-in that records the fields of appended messages and returns a
 * fixed snapshot.
 */
class ServiceClientFakeModel extends \Phalcon\Mvc\Model
{
    public $kind;
    public $clientId;
    public $status;
    public $scopes;

    /**
     * Fields of the messages appended by the behavior.
     *
     * @var array
     */
    public $messages = [];

    /**
     * @var array
     */
    private $fakeSnapshot;

    /**
     * @param array $attributes
     * @param array $snapshot
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

    public function readAttribute(string $attribute)
    {
        return $this->$attribute;
    }

    public function writeAttribute(string $attribute, $value): void
    {
        $this->$attribute = $value;
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
