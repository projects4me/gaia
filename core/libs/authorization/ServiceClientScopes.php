<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Authorization;

use Gaia\MVC\Models\Serviceclient;

/**
 * The scopes of service clients: the kinds of service and the scopes each
 * kind gets by default (`serviceClients` in `config/serviceclients.php`), and
 * the stored form of a client's scopes, a JSON list of scope names.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Libraries
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceClientScopes
{
    /**
     * The `serviceClients` configuration.
     *
     * @var array
     */
    private $config;

    /**
     * @param array|null $config The `serviceClients` configuration; read from
     *                           the application config when omitted.
     */
    public function __construct(array $config = null)
    {
        $this->config = $config === null ? self::loadConfig() : $config;
    }

    /**
     * The kinds of service that may have a client.
     *
     * @return string[]
     */
    public function kinds()
    {
        return array_keys($this->kindsConfig());
    }

    /**
     * The scopes a client of this kind gets when it has none of its own.
     *
     * @param  string $kind
     * @return string[]|null Null for an unknown kind.
     */
    public function defaultsFor($kind)
    {
        $kinds = $this->kindsConfig();
        if (!isset($kinds[$kind])) {
            return null;
        }

        $scopes = isset($kinds[$kind]['scopes']) ? $kinds[$kind]['scopes'] : [];
        return array_values(array_map('strval', (array) $scopes));
    }

    /**
     * The scope names in a stored scope list.
     *
     * @param  mixed $stored JSON text of a list of names.
     * @return string[]|null Null when it is not a list of non-empty names.
     */
    public static function decode($stored)
    {
        $scopes = json_decode((string) $stored, true);
        if (!is_array($scopes) || array_values($scopes) !== $scopes) {
            return null;
        }

        foreach ($scopes as $scope) {
            if (!is_string($scope) || !preg_match('/^[^\s"]+$/', $scope)) {
                return null;
            }
        }

        return $scopes;
    }

    /**
     * The client's scopes as an OAuth scope string (names separated by spaces).
     *
     * @param  Serviceclient $client
     * @return string
     */
    public static function tokenScope(Serviceclient $client)
    {
        $scopes = self::decode($client->scopes);
        return $scopes ? implode(' ', $scopes) : '';
    }

    /**
     * @return array
     */
    private function kindsConfig()
    {
        return isset($this->config['kinds']) && is_array($this->config['kinds']) ? $this->config['kinds'] : [];
    }

    /**
     * @return array
     */
    private static function loadConfig()
    {
        $config = \Phalcon\Di::getDefault()->get('config')->get('serviceClients');
        if ($config instanceof \Phalcon\Config) {
            return $config->toArray();
        }

        return is_array($config) ? $config : [];
    }
}
