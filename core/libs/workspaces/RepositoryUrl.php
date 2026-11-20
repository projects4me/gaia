<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

/**
 * Checks the git URL of a workspace repository: an https or ssh URL, or the
 * scp-like ssh form (`git@host:owner/repo.git`). Gaia stores no secrets, so a
 * URL that carries a password or token is refused. A bare ssh user (`git@`)
 * is not a secret and is allowed; an https URL may carry no user info at all.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class RepositoryUrl
{
    public const INVALID_ERROR = 'url must be an https or ssh git URL, such as https://github.com/owner/repo.git or git@github.com:owner/repo.git';

    public const CREDENTIALS_ERROR = 'url must not contain credentials; tokens and keys stay on the runtime';

    private const SCP_LIKE = '/^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+:(?!\/\/)[^\s:]+$/';

    /**
     * The error for a URL, or null when it is acceptable.
     *
     * @param  mixed $url
     * @return string|null
     */
    public function error($url)
    {
        $url = is_string($url) ? $url : '';
        if ($url === '' || preg_match('/\s/', $url)) {
            return self::INVALID_ERROR;
        }

        if (preg_match(self::SCP_LIKE, $url)) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return self::INVALID_ERROR;
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['https', 'ssh'], true)) {
            return self::INVALID_ERROR;
        }

        if (isset($parts['pass']) || ($scheme === 'https' && isset($parts['user']))) {
            return self::CREDENTIALS_ERROR;
        }

        return null;
    }
}
