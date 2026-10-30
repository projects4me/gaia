<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * A workflow's scope: the projects whose events it runs on. An empty scope
 * covers every project.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowScope
{
    /**
     * The project ids of a stored scope (a JSON list); anything else reads as empty.
     *
     * @param  mixed $value
     * @return string[]
     */
    public static function decode($value)
    {
        $scope = is_array($value) ? $value : json_decode((string) $value, true);
        if (!is_array($scope)) {
            return [];
        }

        return array_values(array_map('strval', array_filter($scope, 'is_scalar')));
    }

    /**
     * Whether a scope covers an event in the project. An event with no
     * project is covered only by an empty scope.
     *
     * @param  string[]    $scope
     * @param  string|null $projectId
     * @return bool
     */
    public static function covers(array $scope, $projectId)
    {
        if ($scope === []) {
            return true;
        }

        return $projectId !== null && $projectId !== '' && in_array((string) $projectId, $scope, true);
    }
}
