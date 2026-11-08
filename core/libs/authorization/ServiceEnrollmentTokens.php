<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

use Gaia\MVC\Models\Serviceenrollmenttoken;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Single-use enrollment tokens (D-070): an admin issues a token for a kind of
 * service, a deploy pipeline hands it to the container, and a Serviceenrollment
 * request that carries it is approved at once. `issue()` is the admin's side
 * and returns the token once; `redeem()` is the service's side and spends it.
 * The token is stored only as a SHA-256 hash.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceEnrollmentTokens
{
    /**
     * The one error for every token that cannot be used, so the reason (unknown,
     * used, expired, revoked, other kind) is not disclosed.
     */
    public const INVALID_ERROR = 'The enrollment token is not valid.';

    /**
     * Minutes a token stays usable.
     */
    public const TTL_MINUTES = 15;

    /**
     * @var EnrollmentCodes
     */
    private $codes;

    /**
     * @param EnrollmentCodes|null $codes
     */
    public function __construct(EnrollmentCodes $codes = null)
    {
        $this->codes = $codes ?: new EnrollmentCodes();
    }

    /**
     * Make a token. The current user is recorded as its creator.
     *
     * @param  mixed $name
     * @param  mixed $kind
     * @return array ['token' => Serviceenrollmenttoken, 'secret' => string]
     * @throws \Gaia\Exception\BadRequest When the token is not valid
     */
    public function issue($name, $kind)
    {
        $secret = $this->codes->pollToken();

        $token = $this->newToken();
        $token->id = create_guid();
        $token->name = is_string($name) ? trim($name) : '';
        $token->kind = is_string($kind) ? $kind : '';
        $token->tokenHash = EnrollmentCodes::hash($secret);
        $token->expiresAt = gmdate('Y-m-d H:i:s', $this->now() + 60 * self::TTL_MINUTES);

        if (!$token->save()) {
            $messages = [];
            foreach ($token->getMessages() as $message) {
                $messages[] = $message->getMessage();
            }
            throw new \Gaia\Exception\BadRequest(implode(' ', $messages) ?: 'The enrollment token could not be saved.');
        }

        return ['token' => $token, 'secret' => $secret];
    }

    /**
     * Spend a token for a kind of service. Locks the token row, so two
     * requests with one token cannot both spend it; call inside a transaction.
     *
     * @param  mixed  $secret
     * @param  string $kind
     * @return Serviceenrollmenttoken The spent token
     * @throws \Gaia\Exception\BadRequest When the token is unknown, used, expired, revoked or of another kind
     */
    public function redeem($secret, $kind)
    {
        $token = $this->find($secret);
        $now = $this->now();

        if (!$token
            || (string) $token->usedAt !== ''
            || (string) $token->kind !== (string) $kind
            || strtotime((string) $token->expiresAt . ' UTC') <= $now
        ) {
            throw new \Gaia\Exception\BadRequest(self::INVALID_ERROR);
        }

        $token->usedAt = gmdate('Y-m-d H:i:s', $now);
        if (!$token->save()) {
            throw new \Gaia\Exception\BadRequest(self::INVALID_ERROR);
        }

        return $token;
    }

    /**
     * @return int Unix time
     */
    protected function now()
    {
        return time();
    }

    /**
     * @return Serviceenrollmenttoken
     */
    protected function newToken()
    {
        return new Serviceenrollmenttoken();
    }

    /**
     * The live token for a secret, row locked.
     *
     * @param  mixed $secret
     * @return Serviceenrollmenttoken|null
     */
    protected function find($secret)
    {
        if (!is_string($secret) || $secret === '') {
            return null;
        }

        $token = Serviceenrollmenttoken::findFirst([
            'conditions' => 'tokenHash = :hash: AND deleted = false',
            'bind' => ['hash' => EnrollmentCodes::hash($secret)],
            'for_update' => true,
        ]);

        return $token ?: null;
    }
}
