<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\Exception\UnAuthorized;

/**
 * Checks that a request comes from the Ares service: it must carry the
 * shared secret from `config/ares.php` (`ARES_INTROSPECTION_SECRET`) in the
 * `X-Ares-Secret` header. With no secret configured every request is refused,
 * so a deployment that has not set one never exposes the endpoint.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Library
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AresCallerGuard
{
    /**
     * The request header that carries the secret.
     */
    public const HEADER = 'X-Ares-Secret';

    /**
     * Error for a request without the right secret.
     */
    public const ERROR = 'Only the Ares service may call this endpoint.';

    /**
     * @var string
     */
    private $secret;

    /**
     * @param string|null $secret The expected secret; null reads it from the config
     */
    public function __construct($secret = null)
    {
        $this->secret = $secret === null ? self::configuredSecret() : (string) $secret;
    }

    /**
     * @param  mixed $presented The secret the caller sent
     * @return void
     * @throws UnAuthorized When no secret is configured or it does not match
     */
    public function assertCaller($presented)
    {
        if ($this->secret === ''
            || !is_string($presented)
            || $presented === ''
            || !hash_equals($this->secret, $presented)
        ) {
            throw new UnAuthorized(self::ERROR);
        }
    }

    /**
     * @return string
     */
    private static function configuredSecret()
    {
        $config = \Phalcon\Di::getDefault()->get('config')->get('ares');

        return $config && isset($config->introspectionSecret) ? (string) $config->introspectionSecret : '';
    }
}
