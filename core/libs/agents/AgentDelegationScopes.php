<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Agents;

use Gaia\Libraries\Authorization\ServiceClientScopes;
use Gaia\Libraries\Security\TokenScope;

/**
 * An agent's delegation scopes (`agents.delegationScopes`): the permission
 * patterns a runtime may act with for the agent (D-067). Stored as a JSON list
 * of permission names with `*` wildcards, the token scope vocabulary (D-064).
 * An empty list means the agent cannot be delegated.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Library
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AgentDelegationScopes
{
    /**
     * The patterns in a stored list, or null when it is not a list of
     * permission patterns. Every pattern must have a dot: a word without one
     * would not narrow a token (D-064), so it would hand over the agent's full ACL.
     *
     * @param  mixed $stored JSON text of a list; null or '' is an empty list.
     * @return string[]|null
     */
    public static function decode($stored)
    {
        if ($stored === null || $stored === '') {
            return [];
        }

        $scopes = ServiceClientScopes::decode($stored);
        if ($scopes === null) {
            return null;
        }

        foreach ($scopes as $scope) {
            if (strpos($scope, '.') === false) {
                return null;
            }
        }

        return $scopes;
    }

    /**
     * The scope a delegated token gets: the requested patterns when every one
     * is covered by the agent's scopes, all of the agent's scopes when none
     * are requested.
     *
     * A requested pattern is covered when one of the agent's patterns matches
     * it as a name (`issue.get` and `issue.*` under `issue.*`, but not `*.get`),
     * so it can never reach more than the agent's scopes do.
     *
     * @param  string[]    $delegationScopes The agent's patterns (non-empty).
     * @param  string|null $requested        Space-separated requested scope.
     * @return string|null Null when a requested pattern is not covered;
     *                     requested patterns come back in lower case.
     */
    public static function grant(array $delegationScopes, $requested)
    {
        $words = preg_split('/\s+/', strtolower(trim((string) $requested)), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === []) {
            return implode(' ', $delegationScopes);
        }

        $patterns = array_map('strtolower', $delegationScopes);
        foreach ($words as $word) {
            if (!TokenScope::matchesAny($patterns, $word)) {
                return null;
            }
        }

        return implode(' ', array_values(array_unique($words)));
    }
}
