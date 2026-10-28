<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

/**
 * The session expiry policy for password-grant sessions.
 *
 * A session is the chain of refresh tokens that starts at one password
 * sign-in. Each refresh token carries its own session window (`rememberMe`
 * and `sessionExpires`), so a later sign-in as the same user can't shorten
 * another session. The window slides: every refresh starts a new window of
 * `sessionTimeoutForRememberMe` (Remember Me) or `sessionTimeout` days, so a
 * session ends only after that many days without a refresh.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SessionExpiry
{
    /**
     * The `oauth` configuration (`sessionTimeout`, `sessionTimeoutForRememberMe`, in days).
     *
     * @var \Phalcon\Config|array
     */
    private $oauthConfig;

    /**
     * @param \Phalcon\Config|array $oauthConfig
     */
    public function __construct($oauthConfig)
    {
        $this->oauthConfig = $oauthConfig;
    }

    /**
     * Returns when a session window opened now ends (UTC, `Y-m-d H:i:s`).
     *
     * @param  bool     $rememberMe
     * @param  int|null $now        Unix time; defaults to the current time.
     * @return string
     * @throws \Exception if the configured timeout isn't a number.
     */
    public function expiresAt($rememberMe, $now = null)
    {
        $key = $rememberMe ? 'sessionTimeoutForRememberMe' : 'sessionTimeout';
        $days = $this->oauthConfig[$key];

        if (!is_numeric($days)) {
            throw new \Exception("Session Timeout should be a number");
        }

        $now = ($now === null) ? time() : $now;
        return gmdate('Y-m-d H:i:s', (int) round($now + $days * 86400));
    }

    /**
     * Whether a session window that ends at `$sessionExpires` is still open.
     *
     * @param  string|null $sessionExpires UTC, `Y-m-d H:i:s`.
     * @param  int|null    $now            Unix time; defaults to the current time.
     * @return bool
     */
    public static function isOpen($sessionExpires, $now = null)
    {
        if (empty($sessionExpires)) {
            return false;
        }

        $now = ($now === null) ? time() : $now;
        return substr((string) $sessionExpires, 0, 19) > gmdate('Y-m-d H:i:s', $now);
    }
}
