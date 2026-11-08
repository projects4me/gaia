<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

use Gaia\Libraries\Oauth\Storage\Pdo;

/**
 * Looks up OAuth access tokens that are still live.
 *
 * Owns where tokens are stored and the expiry rule shared with the resource
 * check: a token without an expiry never expires.
 *
 * @author    Hammad Hassan <gollomer@gmail.com>
 * @package   Gaia\Libraries\Security
 * @category  Security
 * @license   http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class LiveAccessTokens
{
    /**
     * @var \OAuth2\Storage\AccessTokenInterface|null
     */
    private $storage;

    /**
     * @param \OAuth2\Storage\AccessTokenInterface|null $storage Token storage; null uses Gaia's
     */
    public function __construct($storage = null)
    {
        $this->storage = $storage;
    }

    /**
     * The stored token row, or null when the token is empty, unknown or expired.
     *
     * @param  mixed $token
     * @return array|null
     */
    public function find($token)
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        $data = $this->storage()->getAccessToken($token);
        if (!$data || (!empty($data['expires']) && time() > (int) $data['expires'])) {
            return null;
        }

        return $data;
    }

    /**
     * @return \OAuth2\Storage\AccessTokenInterface
     */
    private function storage()
    {
        if ($this->storage === null) {
            $this->storage = new Pdo(
                \Phalcon\Di::getDefault()->get('db')->getInternalHandler(),
                ['user_table' => 'users']
            );
        }

        return $this->storage;
    }
}
