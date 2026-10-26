<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Scaffold;

/**
 * What `scaffold api-cases` was asked for: one REST endpoint, as which profile(s), into
 * which catalog, with which extra assertions.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Scaffold
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CaseRequest
{
    /**
     * @var string
     */
    public $method;

    /**
     * @var string e.g. /api/v1/issue/{id}
     */
    public $path;

    /**
     * @var string|null profile name; null means every profile with a rule on the endpoint (acl) or the default one (client)
     */
    public $profile;

    /**
     * @var string client or acl
     */
    public $catalog;

    /**
     * @var array assertion name => expected value, added to the happy-path expectations
     */
    public $assertions;

    /**
     * @param string $method
     * @param string $endpoint a path (/api/v1/issue/{id}) or a resource name (issue)
     * @param string|null $profile
     * @param string $catalog
     * @param array $assertions
     */
    public function __construct(string $method, string $endpoint, ?string $profile, string $catalog, array $assertions = [])
    {
        $this->method = strtoupper($method);
        $this->path = $endpoint !== '' && $endpoint[0] === '/' ? $endpoint : '/api/v1/' . $endpoint;
        $this->profile = $profile;
        $this->catalog = $catalog;
        $this->assertions = $assertions;
    }

    /**
     * @return bool whether the endpoint addresses one record
     */
    public function isDetail(): bool
    {
        return strpos($this->path, '{') !== false;
    }

    /**
     * Parses `--assert=name=value,other=value`; a value is JSON when it parses as JSON, else a string.
     * A bare name asserts null, to be edited.
     *
     * @param string $list
     * @return array
     */
    public static function parseAssertions(string $list): array
    {
        $assertions = [];
        foreach (preg_split('/,(?=[A-Za-z][\w.]*(?:=|,|$))/', $list) as $item) {
            if ($item === '') {
                continue;
            }
            $parts = explode('=', $item, 2);
            if (!isset($parts[1])) {
                $assertions[$parts[0]] = null;
                continue;
            }
            $decoded = json_decode($parts[1], true);
            $assertions[$parts[0]] = $decoded === null && $parts[1] !== 'null' ? $parts[1] : $decoded;
        }

        return $assertions;
    }
}
