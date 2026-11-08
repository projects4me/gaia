<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\Exception\UnAuthorized;
use Gaia\MVC\Models\Serviceclient;

/**
 * Checks that the caller of token introspection is a service (D-065): its
 * own bearer token must be live, carry no user, belong to an active service
 * client (`ServiceClientGuard`) and hold the scope `token.introspect`.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Security
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IntrospectionCallerGuard
{
    /**
     * The scope a service token needs to introspect tokens.
     */
    public const SCOPE = 'token.introspect';

    /**
     * Error for a caller that is not a service allowed to introspect.
     */
    public const ERROR = 'Only a service with the token.introspect scope may call this endpoint.';

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
     * The service client that calls, or a rejection.
     *
     * @param  mixed $callerToken The caller's own bearer token
     * @return Serviceclient
     * @throws UnAuthorized
     */
    public function assertCaller($callerToken)
    {
        $data = $this->tokens->find($callerToken);
        if (!$data
            || (isset($data['user_id']) && (string) $data['user_id'] !== '')
            || !$this->hasScope(isset($data['scope']) ? $data['scope'] : '')
        ) {
            throw new UnAuthorized(self::ERROR);
        }

        try {
            return ServiceClientGuard::assertClientCredentialsAllowed($data['client_id']);
        } catch (UnAuthorized $e) {
            throw new UnAuthorized(self::ERROR);
        }
    }

    /**
     * Whether the space-separated scope holds `token.introspect` itself.
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
