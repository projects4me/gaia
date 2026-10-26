<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

/**
 * Extracts the api-tester fixtures (`tools/api-tester/fixtures/default.json`):
 * the auth profile names and the placeholder value keys. Credentials are
 * deliberately left out.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FixtureExtractor
{
    public const SOURCE = 'tools/api-tester/fixtures/default.json';

    /**
     * Profile name for cases with `"auth": true` (the fixture's main `auth` user).
     */
    public const DEFAULT_PROFILE = 'default';

    /**
     * @var string
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @return array with the keys file, profiles (names, the default profile
     *  first) and values (placeholder keys, sorted)
     */
    public function extract(): array
    {
        $fixtures = SourceFile::readJson($this->root . '/' . self::SOURCE);

        $profiles = isset($fixtures['authProfiles']) ? array_keys($fixtures['authProfiles']) : [];
        sort($profiles);
        if (isset($fixtures['auth'])) {
            array_unshift($profiles, self::DEFAULT_PROFILE);
        }

        $values = isset($fixtures['values']) ? array_keys($fixtures['values']) : [];
        sort($values);

        return [
            'file' => self::SOURCE,
            'profiles' => $profiles,
            'values' => $values,
        ];
    }
}
