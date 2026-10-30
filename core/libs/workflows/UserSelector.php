<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workflows;

/**
 * Turns a user setting of a node into a member of the run's project. The
 * setting is a user id, an expression naming one (`{issue.assignee}`,
 * `{actor.id}`), a role in the project (`{role:Developer}`: its
 * longest-standing member with that role) or an agent's sponsor
 * (`{sponsor:issue.assignee}`). Whoever it names must be a member of the
 * project the run is for.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workflows
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class UserSelector
{
    /**
     * @var ProjectMembers
     */
    private $members;

    /**
     * @param ProjectMembers|null $members
     */
    public function __construct(ProjectMembers $members = null)
    {
        $this->members = $members ?: new ProjectMembers();
    }

    /**
     * The expression functions that name users, for ExpressionResolver.
     *
     * @return callable[]
     */
    public function functions()
    {
        return [
            'role' => function ($role, array $values) {
                return $this->members->firstWithRole($this->projectOf($values), $role);
            },
            'sponsor' => function ($path, array $values) {
                $userId = ExpressionResolver::path($values, $path);
                return is_scalar($userId) && $userId !== '' ? $this->members->sponsorOf((string) $userId) : null;
            },
        ];
    }

    /**
     * The id of the project member a setting names.
     *
     * @param  string             $name    The setting's name, for the error.
     * @param  mixed              $setting
     * @param  array              $values  The run's values.
     * @param  ExpressionResolver $expressions
     * @return string
     * @throws WorkflowStepException When it names nobody or someone outside the project.
     */
    public function select($name, $setting, array $values, ExpressionResolver $expressions)
    {
        $userId = $expressions->resolve(is_string($setting) ? trim($setting) : $setting, $values);
        if (!is_scalar($userId) || trim((string) $userId) === '') {
            throw new WorkflowStepException("config.{$name} names no user.");
        }

        $userId = trim((string) $userId);
        if (!$this->members->isMember($this->projectOf($values), $userId)) {
            throw new WorkflowStepException("config.{$name} names user {$userId}, who is not a member of the project.");
        }

        return $userId;
    }

    /**
     * @param  array $values
     * @return string
     * @throws WorkflowStepException When the run has no project.
     */
    private function projectOf(array $values)
    {
        if (empty($values['projectId'])) {
            throw new WorkflowStepException('The run has no project to pick users from.');
        }

        return (string) $values['projectId'];
    }
}
