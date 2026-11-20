<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\Exception\Access;
use Gaia\Exception\UnAuthorized;
use Gaia\MVC\Models\Serviceclient;

/**
 * Checks that the caller of GET /api/v1/searchpending is the Ares service (search D-009): its own bearer token must
 * be live, carry no user and hold the scope `searchpending.get`, and its client must be an active service client of
 * kind `ares` (`ServiceClientGuard`). No token, a dead one or one whose client is no longer active is 401; any other
 * caller is 403.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Foundation
 * @category Security
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PendingEmbeddingsCallerGuard
{
    /**
     * The scope the Ares service's token needs.
     */
    public const SCOPE = 'searchpending.get';

    /**
     * The only service kind that may call.
     */
    public const KIND = 'ares';

    /**
     * Error for a request without a live token.
     */
    public const NO_TOKEN_ERROR = 'A valid access token is required.';

    /**
     * Error for a caller that is not the Ares service.
     */
    public const ERROR = 'Only the Ares service may list the records whose embedding is pending.';

    /**
     * @var LiveAccessTokens
     */
    private $tokens;

    /**
     * @param \OAuth2\Storage\AccessTokenInterface|null $storage Token storage; null uses Gaia's
     */
    public function __construct($storage = null)
    {
        $this->tokens = new LiveAccessTokens($storage);
    }

    /**
     * The Ares service client that calls, or a rejection.
     *
     * @param  mixed $callerToken The caller's own bearer token
     * @return Serviceclient
     * @throws UnAuthorized When the token is missing, unknown or expired, or its client is no longer active
     * @throws Access When the caller is a user, another service or lacks the scope
     */
    public function assertCaller($callerToken)
    {
        $data = $this->tokens->find($callerToken);
        if (!$data) {
            throw new UnAuthorized(self::NO_TOKEN_ERROR);
        }
        if (isset($data['user_id']) && (string) $data['user_id'] !== '') {
            throw new Access(self::ERROR);
        }

        // A disabled or deleted client's tokens stop working (D-066): 401, as for any dead token, before
        // the scope or kind is looked at
        $client = $this->serviceClient(isset($data['client_id']) ? (string) $data['client_id'] : '');
        if (!$this->hasScope(isset($data['scope']) ? $data['scope'] : '')
            || (string) $client->kind !== self::KIND
        ) {
            throw new Access(self::ERROR);
        }

        return $client;
    }

    /**
     * The active service client the token was issued to.
     *
     * @param  string $clientId
     * @return Serviceclient
     * @throws UnAuthorized When it is not an active service client
     */
    protected function serviceClient($clientId)
    {
        return ServiceClientGuard::assertClientCredentialsAllowed($clientId);
    }

    /**
     * Whether the space-separated scope holds the scope word itself.
     *
     * @param  mixed $scope
     * @return bool
     */
    private function hasScope($scope)
    {
        $words = preg_split('/\s+/', trim((string) $scope), -1, PREG_SPLIT_NO_EMPTY);

        return in_array(self::SCOPE, $words, true);
    }
}
