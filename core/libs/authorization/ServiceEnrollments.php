<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

use Gaia\MVC\Models\Serviceenrollment;
use Gaia\MVC\Models\User;

use function Gaia\Libraries\Utils\create_guid;

/**
 * The service's side of enrollment (device-authorization style, RFC 8628):
 * a service asks for a client and gets a user code to show and a poll token;
 * it then polls its request with the token, and once an admin has approved the
 * request it receives its client id and secret, once.
 *
 * Both steps run without a signed-in user. The request is saved as the system
 * user; the delivered client is created as the admin who approved it. The
 * admin's side (approve, deny) is a status change checked by
 * `serviceEnrollmentBehavior`. See rbac decision D-066.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceEnrollments
{
    /**
     * Error for an unknown request or a wrong poll token (the same for both,
     * so ids cannot be probed).
     */
    public const NOT_FOUND_ERROR = 'No enrollment request matches this id and poll token.';

    /**
     * Minutes a request waits when the configuration does not say.
     */
    private const DEFAULT_TTL_MINUTES = 15;

    /**
     * @var array The `serviceClients.enrollment` configuration
     */
    private $config;

    /**
     * @var EnrollmentCodes
     */
    private $codes;

    /**
     * @var ServiceClientCredentials
     */
    private $credentials;

    /**
     * @var EnrollmentRequestLimit
     */
    private $limit;

    /**
     * @var ActingUser
     */
    private $actingUser;

    /**
     * @var ServiceEnrollmentTokens
     */
    private $tokens;

    /**
     * @param array|null                    $config      `serviceClients.enrollment`; read from the application config when omitted
     * @param EnrollmentCodes|null          $codes
     * @param ServiceClientCredentials|null $credentials
     * @param EnrollmentRequestLimit|null   $limit
     * @param ActingUser|null               $actingUser
     * @param ServiceEnrollmentTokens|null  $tokens
     */
    public function __construct(
        array $config = null,
        EnrollmentCodes $codes = null,
        ServiceClientCredentials $credentials = null,
        EnrollmentRequestLimit $limit = null,
        ActingUser $actingUser = null,
        ServiceEnrollmentTokens $tokens = null
    ) {
        $this->config = $config === null ? self::loadConfig() : $config;
        $this->codes = $codes ?: new EnrollmentCodes();
        $this->credentials = $credentials ?: new ServiceClientCredentials();
        $this->limit = $limit ?: new EnrollmentRequestLimit(
            isset($this->config['requestsPerIp']) ? $this->config['requestsPerIp'] : EnrollmentRequestLimit::DEFAULT_REQUESTS_PER_IP,
            isset($this->config['windowMinutes']) ? $this->config['windowMinutes'] : EnrollmentRequestLimit::DEFAULT_WINDOW_MINUTES
        );
        $this->actingUser = $actingUser ?: new ActingUser();
        $this->tokens = $tokens ?: new ServiceEnrollmentTokens();
    }

    /**
     * Create a request: pending, or, with a valid enrollment token of the
     * same kind, approved at once on behalf of the admin who issued the token
     * (D-070). The token is spent in the same transaction; an invalid token
     * is refused without saying why.
     *
     * @param  string      $name            The service's name, for the admin and its client
     * @param  string      $kind            A configured kind of service
     * @param  string      $ip              The address the request came from
     * @param  string|null $enrollmentToken A single-use token from `ServiceEnrollmentTokens`
     * @return array ['enrollment' => Serviceenrollment, 'pollToken' => string]
     * @throws \Gaia\Exception\TooManyRequests When the address has made too many requests
     * @throws \Gaia\Exception\BadRequest      When the request is not valid
     */
    public function request($name, $kind, $ip, $enrollmentToken = null)
    {
        if ($enrollmentToken === null) {
            return $this->create($name, $kind, $ip, null);
        }

        return $this->transactional(function () use ($name, $kind, $ip, $enrollmentToken) {
            // The limit comes first so an address that used up its window
            // cannot keep trying tokens.
            $this->limit->assertAllowed($ip, $this->now());
            $token = $this->tokens->redeem($enrollmentToken, is_string($kind) ? $kind : '');
            $issuer = $this->findUser($token->createdUser);
            if (!$issuer) {
                // Never approve with more authority than the issuer had.
                throw new \Gaia\Exception\BadRequest(ServiceEnrollmentTokens::INVALID_ERROR);
            }

            return $this->create($name, $kind, $ip, $issuer);
        });
    }

    /**
     * Save a request, and approve it as the given admin when there is one.
     *
     * @param  mixed     $name
     * @param  mixed     $kind
     * @param  string    $ip
     * @param  User|null $approver
     * @return array
     */
    private function create($name, $kind, $ip, $approver)
    {
        $now = $this->now();
        $this->limit->assertAllowed($ip, $now);

        $pollToken = $this->codes->pollToken();

        $enrollment = $this->newEnrollment();
        $enrollment->id = create_guid();
        $enrollment->name = is_string($name) ? trim($name) : '';
        $enrollment->kind = is_string($kind) ? $kind : '';
        $enrollment->status = ServiceEnrollmentStatus::PENDING;
        $enrollment->userCode = $this->codes->userCode();
        $enrollment->pollTokenHash = EnrollmentCodes::hash($pollToken);
        $enrollment->expiresAt = gmdate('Y-m-d H:i:s', $now + 60 * $this->ttlMinutes());
        $enrollment->requesterIp = (string) $ip;
        $this->saveAs($enrollment, $this->systemUser());

        if ($approver) {
            $enrollment->status = ServiceEnrollmentStatus::APPROVED;
            $this->saveAs($enrollment, $approver);
        }

        return ['enrollment' => $enrollment, 'pollToken' => $pollToken];
    }

    /**
     * Read a request with its poll token. A request past its expiry is marked
     * expired. An approved request is delivered: its service client is
     * created and the client id, secret and service client record id are returned, this once.
     *
     * @param  string $id
     * @param  mixed  $pollToken
     * @return array ['enrollment' => Serviceenrollment, 'clientId' => string|null, 'secret' => string|null, 'serviceClientId' => string|null]
     * @throws \Gaia\Exception\ResourceNotFound When no request matches the id and token
     */
    public function poll($id, $pollToken)
    {
        return $this->transactional(function () use ($id, $pollToken) {
            // Locks the row, so two polls of one approved request deliver one secret.
            $enrollment = $this->findLive($id, true);
            if (!$enrollment || !EnrollmentCodes::matches($enrollment->pollTokenHash, $pollToken)) {
                throw new \Gaia\Exception\ResourceNotFound(self::NOT_FOUND_ERROR);
            }

            $result = ['enrollment' => $enrollment, 'clientId' => null, 'secret' => null, 'serviceClientId' => null];
            $now = $this->now();
            $current = ServiceEnrollmentStatus::current($enrollment->status, $enrollment->expiresAt, $now);

            if ($current === ServiceEnrollmentStatus::EXPIRED && $enrollment->status !== $current) {
                $enrollment->status = ServiceEnrollmentStatus::EXPIRED;
                $this->saveAs($enrollment, $this->systemUser());
            } elseif ($current === ServiceEnrollmentStatus::APPROVED) {
                $approver = $this->findUser($enrollment->approvedBy) ?: $this->systemUser();
                $issued = $this->runAs($approver, function () use ($enrollment) {
                    return $this->credentials->issue($enrollment->name, $enrollment->kind);
                });

                $enrollment->status = ServiceEnrollmentStatus::DELIVERED;
                $enrollment->serviceclientId = $issued['serviceclient']->id;
                $this->saveAs($enrollment, $approver);

                $result['clientId'] = $issued['clientId'];
                $result['secret'] = $issued['secret'];
                $result['serviceClientId'] = (string) $issued['serviceclient']->id;
            }

            return $result;
        });
    }

    /**
     * The current time.
     *
     * @return int Unix time
     */
    protected function now()
    {
        return time();
    }

    /**
     * @return Serviceenrollment
     */
    protected function newEnrollment()
    {
        return new Serviceenrollment();
    }

    /**
     * A request that is not deleted.
     *
     * @param  mixed $id
     * @param  bool  $lock Lock the row until the transaction ends
     * @return Serviceenrollment|null
     */
    protected function findLive($id, $lock)
    {
        if (!is_string($id) || $id === '') {
            return null;
        }

        $enrollment = Serviceenrollment::findFirst([
            'conditions' => 'id = :id: AND deleted = false',
            'bind' => ['id' => $id],
            'for_update' => (bool) $lock,
        ]);

        return $enrollment ?: null;
    }

    /**
     * @param  mixed $userId
     * @return User|null
     */
    protected function findUser($userId)
    {
        if ((string) $userId === '') {
            return null;
        }

        $user = User::findFirst([
            'conditions' => 'id = :id:',
            'bind' => ['id' => (string) $userId],
        ]);

        return $user ?: null;
    }

    /**
     * The user that anonymous enrollment writes are recorded as.
     *
     * @return User|null
     */
    protected function systemUser()
    {
        return User::getSystemUser();
    }

    /**
     * Run work in one database transaction.
     *
     * @param  callable $work
     * @return mixed
     */
    protected function transactional(callable $work)
    {
        $db = \Phalcon\Di::getDefault()->getShared('db');
        $db->begin();

        try {
            $result = $work();
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            try {
                $db->rollback();
            } catch (\Throwable $ignored) {
                // No open transaction (or already rolled back).
            }
            throw $e;
        }
    }

    /**
     * Run work with the given user as the current user (`ActingUser`).
     *
     * @param  User|null $user
     * @param  callable  $work
     * @return mixed
     */
    protected function runAs($user, callable $work)
    {
        return $this->actingUser->run($user, $work);
    }

    /**
     * Save the request as the given user (`ActingUser`).
     *
     * @param  Serviceenrollment $enrollment
     * @param  User|null         $user
     * @return void
     * @throws \Gaia\Exception\BadRequest
     */
    protected function saveAs($enrollment, $user)
    {
        $this->actingUser->save($enrollment, $user, 'The enrollment request could not be saved.');
    }

    /**
     * @return int
     */
    private function ttlMinutes()
    {
        $minutes = isset($this->config['ttlMinutes']) ? (int) $this->config['ttlMinutes'] : 0;
        return $minutes > 0 ? $minutes : self::DEFAULT_TTL_MINUTES;
    }

    /**
     * @return array
     */
    private static function loadConfig()
    {
        $config = \Phalcon\Di::getDefault()->get('config')->get('serviceClients');
        if ($config instanceof \Phalcon\Config) {
            $config = $config->toArray();
        }

        return is_array($config) && isset($config['enrollment']) && is_array($config['enrollment'])
            ? $config['enrollment']
            : [];
    }
}
