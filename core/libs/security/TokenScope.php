<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Security;

/**
 * The scope of an access token, as a limit on top of the actor's ACL (D-064).
 *
 * A scope word that names a permission (`issue.get`) or a pattern of them with
 * `*` wildcards (`issue.*`, `*.get`) narrows the token: it may then reach only
 * the action resources one of its patterns covers, and never the hard
 * exclusions (credentials, enrollment, roles and permissions, agent writes),
 * whatever its patterns say. Words without a dot, such as the legacy OAuth
 * default scope `application`, are not permissions and do not narrow: a token
 * with only those is unscoped and keeps the actor's full ACL.
 *
 * Scopes only narrow; whether the actor holds the permission is still the
 * ACL's decision.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Security
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TokenScope
{
    /**
     * Action resources a scoped token can never reach, as scope patterns.
     *
     * @var string[]
     */
    const EXCLUDED = [
        'agentcredential.*',
        'serviceclient.*',
        'enrollment*.*',
        'role.*',
        'userrole.*',
        'permission.*',
        'agent.create',
        'agent.update',
        'agent.delete',
    ];

    /**
     * Permission patterns of the token, lower case. Empty when unscoped.
     *
     * @var string[]
     */
    protected $patterns = [];

    /**
     * @param string|null $scope The token's space-separated OAuth scope.
     */
    public function __construct($scope)
    {
        $words = preg_split('/\s+/', strtolower(trim((string) $scope)), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $word) {
            if (strpos($word, '.') !== false) {
                $this->patterns[] = $word;
            }
        }
    }

    /**
     * Whether the token carries permission scopes that narrow its access.
     *
     * @return bool
     */
    public function isScoped()
    {
        return $this->patterns !== [];
    }

    /**
     * Whether the token may reach an action resource (e.g. `issue.get`).
     * Always true for an unscoped token.
     *
     * @param  string $resourceName
     * @return bool
     */
    public function covers($resourceName)
    {
        if (!$this->isScoped()) {
            return true;
        }

        $resourceName = strtolower((string) $resourceName);
        if (self::matchesAny(self::EXCLUDED, $resourceName)) {
            return false;
        }

        return self::matchesAny($this->patterns, $resourceName);
    }

    /**
     * Whether any pattern matches the resource; `*` matches within one segment.
     *
     * @param  string[] $patterns
     * @param  string   $resourceName
     * @return bool
     */
    protected static function matchesAny(array $patterns, $resourceName)
    {
        foreach ($patterns as $pattern) {
            $regex = '/^' . str_replace('\*', '[^.]*', preg_quote($pattern, '/')) . '$/';
            if (preg_match($regex, $resourceName) === 1) {
                return true;
            }
        }

        return false;
    }
}
