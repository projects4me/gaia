<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Authorization;

use Gaia\Libraries\Authorization\EnrollmentCodes;
use Gaia\Libraries\Authorization\EnrollmentRequestLimit;
use Gaia\Libraries\Authorization\ServiceClientCredentials;
use Gaia\Libraries\Authorization\ServiceEnrollments;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ServiceEnrollments: a request is pending with a hashed poll
 * token and a 15-minute expiry; the rate limit refuses an address that has
 * used its window; a poll needs the right token, delivers an approved request
 * once, and expires a request past its time.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceEnrollmentsTest extends TestCase
{
    private const NOW = 1800000000;

    private const TOKEN = 'the-poll-token';

    public function testARequestIsPendingWithAHashedTokenAndExpiresIn15Minutes()
    {
        $service = $this->service();

        $requested = $service->request(' Talos runtime ', 'runtime', '10.0.0.7');
        $enrollment = $requested['enrollment'];

        $this->assertSame(self::TOKEN, $requested['pollToken']);
        $this->assertSame('Talos runtime', $enrollment->name);
        $this->assertSame('runtime', $enrollment->kind);
        $this->assertSame('pending', $enrollment->status);
        $this->assertSame('KQ7-3MX', $enrollment->userCode);
        $this->assertSame(hash('sha256', self::TOKEN), $enrollment->pollTokenHash);
        $this->assertSame(gmdate('Y-m-d H:i:s', self::NOW + 900), $enrollment->expiresAt);
        $this->assertSame('10.0.0.7', $enrollment->requesterIp);
        $this->assertSame([['enrollment', 'system']], $service->saves);
    }

    public function testAnAddressOverItsLimitIsRefused()
    {
        $service = $this->service(['requestsSince' => 5]);

        try {
            $service->request('Talos runtime', 'runtime', '10.0.0.7');
            $this->fail('Expected TooManyRequests');
        } catch (\Gaia\Exception\TooManyRequests $e) {
            $this->assertSame(EnrollmentRequestLimit::LIMIT_ERROR, $e->getMessage());
            $this->assertSame(900, $e->getRetryAfter());
        }
        $this->assertSame([], $service->saves);
    }

    public function testAWrongPollTokenIsNotFound()
    {
        $service = $this->service(['stored' => $this->enrollment('approved')]);

        foreach (['wrong-token', '', null, ['x']] as $token) {
            try {
                $service->poll('enr-1', $token);
                $this->fail('Expected ResourceNotFound');
            } catch (\Gaia\Exception\ResourceNotFound $e) {
                $this->assertSame(ServiceEnrollments::NOT_FOUND_ERROR, $e->getMessage());
            }
        }
        $this->assertSame(0, $service->credentials->issued);
    }

    public function testAnUnknownRequestIsNotFound()
    {
        $this->expectException(\Gaia\Exception\ResourceNotFound::class);

        $this->service()->poll('enr-missing', self::TOKEN);
    }

    public function testAPendingRequestGivesNoSecret()
    {
        $service = $this->service(['stored' => $this->enrollment('pending')]);

        $polled = $service->poll('enr-1', self::TOKEN);

        $this->assertSame('pending', $polled['enrollment']->status);
        $this->assertNull($polled['secret']);
        $this->assertNull($polled['clientId']);
        $this->assertSame([], $service->saves);
    }

    public function testAnApprovedRequestIsDeliveredOnce()
    {
        $enrollment = $this->enrollment('approved');
        $service = $this->service(['stored' => $enrollment]);

        $first = $service->poll('enr-1', self::TOKEN);
        $second = $service->poll('enr-1', self::TOKEN);

        $this->assertSame('service-abc', $first['clientId']);
        $this->assertSame('p4m_cs_secret', $first['secret']);
        $this->assertSame('delivered', $enrollment->status);
        $this->assertSame('svc-1', $enrollment->serviceclientId);
        $this->assertSame([['Talos runtime', 'runtime', 'admin-1']], $service->credentials->calls);
        $this->assertSame([['enrollment', 'admin-1']], $service->saves);

        $this->assertSame('delivered', $second['enrollment']->status);
        $this->assertNull($second['secret']);
        $this->assertSame(1, $service->credentials->issued);
        $this->assertSame(2, $service->transactions);
    }

    public function testAnApprovedRequestPastItsExpiryIsExpiredWithoutASecret()
    {
        $enrollment = $this->enrollment('approved', -1);
        $service = $this->service(['stored' => $enrollment]);

        $polled = $service->poll('enr-1', self::TOKEN);

        $this->assertSame('expired', $enrollment->status);
        $this->assertNull($polled['secret']);
        $this->assertSame(0, $service->credentials->issued);
        $this->assertSame([['enrollment', 'system']], $service->saves);
    }

    public function testADeniedRequestGivesNoSecret()
    {
        $service = $this->service(['stored' => $this->enrollment('denied')]);

        $polled = $service->poll('enr-1', self::TOKEN);

        $this->assertSame('denied', $polled['enrollment']->status);
        $this->assertNull($polled['secret']);
        $this->assertSame(0, $service->credentials->issued);
    }

    /**
     * @param  string $status
     * @param  int    $expiresIn Seconds from now
     * @return \stdClass
     */
    private function enrollment($status, $expiresIn = 600)
    {
        $enrollment = new \stdClass();
        $enrollment->id = 'enr-1';
        $enrollment->name = 'Talos runtime';
        $enrollment->kind = 'runtime';
        $enrollment->status = $status;
        $enrollment->userCode = 'KQ7-3MX';
        $enrollment->pollTokenHash = hash('sha256', self::TOKEN);
        $enrollment->expiresAt = gmdate('Y-m-d H:i:s', self::NOW + $expiresIn);
        $enrollment->approvedBy = in_array($status, ['approved', 'denied'], true) ? 'admin-1' : null;
        $enrollment->serviceclientId = null;
        return $enrollment;
    }

    /**
     * @param  array $options stored (the request found by id), requestsSince
     * @return FakeServiceEnrollments
     */
    private function service(array $options = [])
    {
        $limit = new FakeEnrollmentRequestLimit(5, 15);
        $limit->count = isset($options['requestsSince']) ? $options['requestsSince'] : 0;

        $service = new FakeServiceEnrollments(
            ['ttlMinutes' => 15, 'requestsPerIp' => 5, 'windowMinutes' => 15],
            new FakeEnrollmentCodes(),
            new FakeServiceClientCredentials(),
            $limit
        );
        $service->stored = isset($options['stored']) ? $options['stored'] : null;
        return $service;
    }
}

/**
 * ServiceEnrollments without the database: the found request, the users and
 * the saves are kept in memory.
 */
class FakeServiceEnrollments extends ServiceEnrollments
{
    public $stored;

    public $saves = [];

    public $transactions = 0;

    public $credentials;

    public function __construct(array $config, $codes, $credentials, $limit)
    {
        parent::__construct($config, $codes, $credentials, $limit);
        $this->credentials = $credentials;
    }

    protected function now()
    {
        return 1800000000;
    }

    protected function newEnrollment()
    {
        return new \stdClass();
    }

    protected function findLive($id, $lock)
    {
        return $this->stored && $this->stored->id === $id ? $this->stored : null;
    }

    protected function findUser($userId)
    {
        return $userId ? (object) ['id' => (string) $userId] : null;
    }

    protected function systemUser()
    {
        return (object) ['id' => 'system'];
    }

    protected function transactional(callable $work)
    {
        $this->transactions++;
        return $work();
    }

    protected function runAs($user, callable $work)
    {
        $this->credentials->actingUser = $user ? $user->id : null;
        return $work();
    }

    protected function saveAs($enrollment, $user)
    {
        $this->saves[] = ['enrollment', $user ? $user->id : null];
    }
}

/**
 * Fixed codes, so the request can be checked.
 */
class FakeEnrollmentCodes extends EnrollmentCodes
{
    public function userCode()
    {
        return 'KQ7-3MX';
    }

    public function pollToken()
    {
        return 'the-poll-token';
    }
}

/**
 * Issues a fixed client and records each call with the acting user.
 */
class FakeServiceClientCredentials extends ServiceClientCredentials
{
    public $issued = 0;

    public $calls = [];

    public $actingUser;

    public function issue($name, $kind)
    {
        $this->issued++;
        $this->calls[] = [$name, $kind, $this->actingUser];
        return ['serviceclient' => (object) ['id' => 'svc-1'], 'clientId' => 'service-abc', 'secret' => 'p4m_cs_secret'];
    }
}

/**
 * A request limit with a fixed count of earlier requests.
 */
class FakeEnrollmentRequestLimit extends EnrollmentRequestLimit
{
    public $count = 0;

    protected function requestsSince($ip, $since)
    {
        return $this->count;
    }
}
