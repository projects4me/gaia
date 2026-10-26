<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Check;

use GaiaDev\Query\GraphReader;
use GaiaDev\Scaffold\ResourceRegistrar;

/**
 * Checks that every touchpoint of a resource is present and agrees with the others: metadata, model class,
 * models list, route, controller, the generated route catalog and the api-cases (acl, placeholders, assertions).
 * A model with no route is internal; it is checked for metadata, class and list entry only.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Check
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ResourceCheck
{
    /**
     * @var string
     */
    private $root;

    /**
     * @var GraphReader
     */
    private $graph;

    /**
     * @param string $root repository root
     * @param GraphReader $graph
     */
    public function __construct(string $root, GraphReader $graph)
    {
        $this->root = rtrim($root, '/\\');
        $this->graph = $graph;
    }

    /**
     * @param string $name model name, e.g. Idea
     * @return string[] the problems, empty when the resource is consistent
     */
    public function problems(string $name): array
    {
        $resource = strtolower($name);
        $problems = [];

        $metadata = $this->read("app/metadata/model/{$name}.php");
        if ($metadata === null || strpos($metadata, "\$models['{$name}']") === false) {
            $problems[] = "metadata: app/metadata/model/{$name}.php is missing or does not define \$models['{$name}']";
        }
        $model = $this->read("app/models/{$name}.php");
        if ($model === null || !preg_match('/class ' . $name . ' extends \w+/', $model)) {
            $problems[] = "model: app/models/{$name}.php is missing or does not declare class {$name}";
        }
        $list = $this->read(ResourceRegistrar::MODELS);
        if ($list === null || !preg_match("/^\\s*'{$name}',?\\s*$/m", $list)) {
            $problems[] = 'models list: ' . ResourceRegistrar::MODELS . " does not list {$name}";
        }

        $route = $this->route($resource);
        if ($route === null) {
            return $problems;
        }
        if (strpos($route, "'controller'") === false) {
            $controller = $this->read("app/api/v1/controllers/{$name}Controller.php");
            if ($controller === null || !preg_match('/class ' . $name . 'Controller extends RestController/', $controller)) {
                $problems[] = "controller: app/api/v1/controllers/{$name}Controller.php is missing or is not a RestController named {$name}Controller";
            }
        }
        $catalog = $this->read('tools/api-tester/sources/server-routes/routes.v1.json');
        if ($catalog !== null && strpos($catalog, "\"{$resource}\": {") === false) {
            $problems[] = "route catalog: routes.v1.json has no {$resource}; run php tools/api-tester/bin/generate-backend-apis.php";
        }

        return array_merge($problems, (new ApiCasesCheck($this->graph))->problems($resource));
    }

    /**
     * @param string $resource
     * @return string|null the resource's entry in routes.php, null when it has none
     */
    private function route(string $resource): ?string
    {
        $routes = $this->read(ResourceRegistrar::ROUTES);
        if ($routes !== null && preg_match('/\n            \'' . $resource . '\' => array\(\n.*?\n            \),/s', $routes, $match)) {
            return $match[0];
        }

        return null;
    }

    /**
     * @param string $relative
     * @return string|null
     */
    private function read(string $relative): ?string
    {
        $file = $this->root . '/' . $relative;

        return is_file($file) ? str_replace("\r\n", "\n", file_get_contents($file)) : null;
    }
}
