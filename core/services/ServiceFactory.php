<?php

namespace Gaia\Services;

use Gaia\Services\LLM;

/**
 * This factory class provides a centralized way to create and instantiate
 * various service objects within the Gaia application. It implements the
 * Factory pattern to encapsulate object creation logic and provide a
 * consistent interface for service instantiation.
 *
 * @package  Gaia\Services
 * @category Factory
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class ServiceFactory
{
    /**
     * This function is used to create service instances based on the service name.
     *
     * @method create
     * @param string $serviceName The name of the service to create (e.g., 'llm')
     *
     * @return object Returns an instance of the requested service
     * @throws \Exception When an invalid service name is provided
     */
    public static function create($serviceName)
    {
        switch ($serviceName) {
            case 'llm':
                return new LLM();
            default:
                throw new \Exception("Invalid service name: " . $serviceName);
        }
    }
}
