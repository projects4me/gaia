<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use PHPUnit\Framework\TestCase;
use Gaia\Libraries\Authorization\ServiceClientScopes;
use Gaia\Libraries\Authorization\ServiceEnrollmentStatus;
use Gaia\MVC\Models\Behaviors\serviceEnrollmentBehavior;

/**
 * Unit tests for serviceEnrollmentBehavior: a new request has a name, a
 * configured kind and the pending status; an admin approves or denies a
 * pending request before it expires and is stored as approvedBy; a request is
 * delivered only with its service client; the request's own fields never change.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceEnrollmentBehaviorTest extends TestCase
{
    private const NOW = 1800000000;

    public function testANewPendingRequestOfAKnownKindIsAccepted()
    {
        $model = ServiceEnrollmentFakeModel::make(['name' => 'Talos', 'kind' => 'runtime', 'status' => 'pending']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnCreate', $model));
        $this->assertSame([], $model->messages);
    }

    public function testANewRequestNeedsANameAKnownKindAndThePendingStatus()
    {
        $cases = [
            'name' => ['name' => '  ', 'kind' => 'runtime', 'status' => 'pending'],
            'kind' => ['name' => 'Talos', 'kind' => 'mailer', 'status' => 'pending'],
            'status' => ['name' => 'Talos', 'kind' => 'runtime', 'status' => 'approved'],
        ];
        foreach ($cases as $field => $attributes) {
            $model = ServiceEnrollmentFakeModel::make($attributes);

            $this->assertFalse($this->behavior()->notify('beforeValidationOnCreate', $model), $field);
            $this->assertSame([$field], $model->messages, $field);
        }
    }

    public function testApprovingAPendingRequestRecordsTheApprover()
    {
        $model = $this->stored('pending', 600, ['status' => 'approved']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame('admin-1', $model->approvedBy);
    }

    public function testDenyingAPendingRequestRecordsWhoDenied()
    {
        $model = $this->stored('pending', 600, ['status' => 'denied']);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame('admin-1', $model->approvedBy);
    }

    public function testAnExpiredRequestCannotBeApproved()
    {
        $model = $this->stored('pending', -1, ['status' => 'approved']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['status'], $model->messages);
        $this->assertNull($model->approvedBy);
    }

    public function testADeliveredRequestCannotBeApprovedAgain()
    {
        $model = $this->stored('delivered', 600, ['status' => 'approved']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['status'], $model->messages);
    }

    public function testDeliveryNeedsTheServiceClient()
    {
        $without = $this->stored('approved', 600, ['status' => 'delivered']);
        $with = $this->stored('approved', 600, ['status' => 'delivered', 'serviceclientId' => 'svc-1']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $without));
        $this->assertSame(['status'], $without->messages);
        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $with));
        $this->assertSame('admin-1', $with->approvedBy);
    }

    public function testTheRequestsOwnFieldsCannotChange()
    {
        $model = $this->stored('pending', 600, ['kind' => 'hermes']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['kind'], $model->messages);
    }

    public function testTheApproverIsSetOnlyByADecision()
    {
        $model = $this->stored('approved', 600, ['approvedBy' => 'someone-else']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['approvedBy'], $model->messages);
    }

    public function testTheServiceClientIsSetOnlyByDelivery()
    {
        $model = $this->stored('pending', 600, ['serviceclientId' => 'svc-1']);

        $this->assertFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
        $this->assertSame(['serviceclientId'], $model->messages);
    }

    public function testAnUpdateWithoutAStatusChangeIsAccepted()
    {
        $model = $this->stored('pending', -1, []);

        $this->assertNotFalse($this->behavior()->notify('beforeValidationOnUpdate', $model));
    }

    /**
     * A stored request (its snapshot) with the given changes applied.
     *
     * @param  string $status
     * @param  int    $expiresIn Seconds from now
     * @param  array  $changes
     * @return ServiceEnrollmentFakeModel
     */
    private function stored($status, $expiresIn, array $changes)
    {
        $snapshot = [
            'name' => 'Talos',
            'kind' => 'runtime',
            'status' => $status,
            'userCode' => 'KQ7-3MX',
            'pollTokenHash' => 'hash',
            'expiresAt' => gmdate('Y-m-d H:i:s', self::NOW + $expiresIn),
            'requesterIp' => '10.0.0.7',
            'approvedBy' => $status === ServiceEnrollmentStatus::PENDING ? null : 'admin-1',
            'serviceclientId' => null,
        ];

        return ServiceEnrollmentFakeModel::make(array_merge($snapshot, $changes), $snapshot);
    }

    /**
     * @return serviceEnrollmentBehavior
     */
    private function behavior()
    {
        return new class extends serviceEnrollmentBehavior {
            protected function scopes()
            {
                return new ServiceClientScopes(['kinds' => [
                    'runtime' => ['scopes' => ['agent.read']],
                    'hermes' => ['scopes' => ['token.introspect']],
                ]]);
            }

            protected function now()
            {
                return 1800000000;
            }

            protected function currentUserId()
            {
                return 'admin-1';
            }
        };
    }
}

/**
 * A model stand-in that keeps attributes, messages and a snapshot in memory.
 */
class ServiceEnrollmentFakeModel extends \Phalcon\Mvc\Model
{
    public $name;
    public $kind;
    public $status;
    public $userCode;
    public $pollTokenHash;
    public $expiresAt;
    public $requesterIp;
    public $approvedBy;
    public $serviceclientId;

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
