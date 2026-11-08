<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Authorization;

use Gaia\Libraries\Authorization\EnrollmentCodes;
use Gaia\Libraries\Authorization\ServiceEnrollments;
use Gaia\Libraries\Authorization\ServiceEnrollmentTokens;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ServiceEnrollmentTokens and for an enrollment request that
 * carries a token (D-070): a token is issued hashed with a 15-minute expiry,
 * is spent once, and is refused, with one error, when it is expired, of
 * another kind, revoked, unknown or already used. A valid token approves the
 * request at once, as the admin who issued the token.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceEnrollmentTokensTest extends TestCase
{
    private const NOW = 1800000000;

    private const SECRET = 'the-enrollment-token';

    public function testATokenIsIssuedHashedAndExpiresIn15Minutes()
    {
        $tokens = new TestableServiceEnrollmentTokens();

        $issued = $tokens->issue(' Hermes deploy ', 'runtime');

        $this->assertSame(self::SECRET, $issued['secret']);
        $this->assertSame('Hermes deploy', $issued['token']->name);
        $this->assertSame('runtime', $issued['token']->kind);
        $this->assertSame(hash('sha256', self::SECRET), $issued['token']->tokenHash);
        $this->assertSame(gmdate('Y-m-d H:i:s', self::NOW + 900), $issued['token']->expiresAt);
        $this->assertSame(1, $issued['token']->saves);
    }

    public function testASaveFailureIsABadRequest()
    {
        $tokens = new TestableServiceEnrollmentTokens();
        $tokens->failSave = true;

        $this->expectException(\Gaia\Exception\BadRequest::class);
        $this->expectExceptionMessage('kind must be one of');

        $tokens->issue('Hermes deploy', 'nope');
    }

    public function testAValidTokenIsSpentOnce()
    {
        $stored = $this->stored();
        $tokens = new TestableServiceEnrollmentTokens($stored);

        $spent = $tokens->redeem(self::SECRET, 'runtime');

        $this->assertSame(gmdate('Y-m-d H:i:s', self::NOW), $spent->usedAt);
        $this->assertSame(1, $stored->saves);
        $this->assertRefused($tokens, self::SECRET, 'runtime');
        $this->assertSame(1, $stored->saves);
    }

    public function testAnExpiredTokenIsRefused()
    {
        $tokens = new TestableServiceEnrollmentTokens($this->stored(['expiresAt' => gmdate('Y-m-d H:i:s', self::NOW)]));

        $this->assertRefused($tokens, self::SECRET, 'runtime');
    }

    public function testATokenOfAnotherKindIsRefusedAndStaysUnused()
    {
        $stored = $this->stored();
        $tokens = new TestableServiceEnrollmentTokens($stored);

        $this->assertRefused($tokens, self::SECRET, 'hermes');
        $this->assertSame('', (string) $stored->usedAt);
        $this->assertSame(0, $stored->saves);
    }

    public function testARevokedOrUnknownTokenIsRefused()
    {
        $tokens = new TestableServiceEnrollmentTokens(null);

        foreach ([self::SECRET, '', null, ['x']] as $secret) {
            $this->assertRefused($tokens, $secret, 'runtime');
        }
    }

    public function testAValidTokenApprovesTheRequestAsItsIssuer()
    {
        $stored = $this->stored();
        $service = new TokenRequestService(new TestableServiceEnrollmentTokens($stored));

        $requested = $service->request('Hermes', 'runtime', '10.0.0.7', self::SECRET);

        $this->assertSame('approved', $requested['enrollment']->status);
        $this->assertSame([['system', 'pending'], ['admin-1', 'approved']], $service->saves);
        $this->assertSame(1, $stored->saves);
    }

    public function testARequestWithoutATokenStaysPending()
    {
        $service = new TokenRequestService(new TestableServiceEnrollmentTokens($this->stored()));

        $requested = $service->request('Hermes', 'runtime', '10.0.0.7');

        $this->assertSame('pending', $requested['enrollment']->status);
        $this->assertSame([['system', 'pending']], $service->saves);
    }

    public function testAnInvalidTokenRefusesTheRequestWithoutSavingIt()
    {
        $service = new TokenRequestService(new TestableServiceEnrollmentTokens($this->stored()));

        try {
            $service->request('Hermes', 'hermes', '10.0.0.7', self::SECRET);
            $this->fail('Expected BadRequest');
        } catch (\Gaia\Exception\BadRequest $e) {
            $this->assertSame(ServiceEnrollmentTokens::INVALID_ERROR, $e->getMessage());
        }
        $this->assertSame([], $service->saves);
    }

    public function testATokenWhoseIssuerIsGoneRefusesTheRequest()
    {
        $service = new TokenRequestService(new TestableServiceEnrollmentTokens($this->stored(['createdUser' => ''])));

        try {
            $service->request('Hermes', 'runtime', '10.0.0.7', self::SECRET);
            $this->fail('Expected BadRequest');
        } catch (\Gaia\Exception\BadRequest $e) {
            $this->assertSame(ServiceEnrollmentTokens::INVALID_ERROR, $e->getMessage());
        }
        $this->assertSame([], $service->saves);
    }

    private function assertRefused($tokens, $secret, $kind)
    {
        try {
            $tokens->redeem($secret, $kind);
            $this->fail('Expected BadRequest');
        } catch (\Gaia\Exception\BadRequest $e) {
            $this->assertSame(ServiceEnrollmentTokens::INVALID_ERROR, $e->getMessage());
        }
    }

    private function stored(array $overrides = [])
    {
        return new FakeTokenModel($overrides + [
            'id' => 'tok-1',
            'kind' => 'runtime',
            'usedAt' => null,
            'createdUser' => 'admin-1',
            'expiresAt' => gmdate('Y-m-d H:i:s', self::NOW + 60),
        ]);
    }
}

/**
 * A token model that records its saves.
 */
class FakeTokenModel
{
    public $saves = 0;

    public $failSave = false;

    public function __construct(array $values = [])
    {
        foreach ($values as $field => $value) {
            $this->$field = $value;
        }
    }

    public function save()
    {
        $this->saves++;
        return !$this->failSave;
    }

    public function getMessages()
    {
        return [new class {
            public function getMessage()
            {
                return 'kind must be one of: runtime';
            }
        }];
    }
}

/**
 * Tokens with a fixed clock and code and a stored token in place of the database.
 */
class TestableServiceEnrollmentTokens extends ServiceEnrollmentTokens
{
    public $failSave = false;

    private $stored;

    public function __construct($stored = null)
    {
        parent::__construct(new FixedSecretCodes());
        $this->stored = $stored;
    }

    protected function now()
    {
        return 1800000000;
    }

    protected function newToken()
    {
        $token = new FakeTokenModel();
        $token->failSave = $this->failSave;
        return $token;
    }

    protected function find($secret)
    {
        return $this->stored && EnrollmentCodes::matches(hash('sha256', 'the-enrollment-token'), $secret)
            ? $this->stored
            : null;
    }
}

/**
 * A fixed secret, so the stored hash can be checked.
 */
class FixedSecretCodes extends EnrollmentCodes
{
    public function pollToken()
    {
        return 'the-enrollment-token';
    }
}

/**
 * ServiceEnrollments without a database: records who saved each status.
 */
class TokenRequestService extends ServiceEnrollments
{
    public $saves = [];

    public function __construct($tokens)
    {
        parent::__construct(
            ['ttlMinutes' => 15],
            new FixedSecretCodes(),
            null,
            new class (5, 15) extends \Gaia\Libraries\Authorization\EnrollmentRequestLimit {
                protected function requestsSince($ip, $since)
                {
                    return 0;
                }
            },
            null,
            $tokens
        );
    }

    protected function now()
    {
        return 1800000000;
    }

    protected function newEnrollment()
    {
        return new \stdClass();
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
        return $work();
    }

    protected function saveAs($enrollment, $user)
    {
        $this->saves[] = [$user->id, $enrollment->status];
    }
}
