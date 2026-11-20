<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

/**
 * Reads the bearer token out of an Authorization header, for the controllers whose caller guards check the caller's
 * own token themselves (introspection, the pending search list).
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Foundation
 * @category Security
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class BearerToken
{
    /**
     * @param  mixed $header The Authorization header, e.g. `Bearer abc`
     * @return string|null The token, or null when the header is not a bearer token
     */
    public static function fromHeader($header)
    {
        if (preg_match('/^Bearer\s+(\S+)$/i', trim((string) $header), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
