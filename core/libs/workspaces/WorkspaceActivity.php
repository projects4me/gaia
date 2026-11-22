<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Workspaces;

use Gaia\MVC\Models\Activity;
use Gaia\MVC\Models\Serviceclient;
use Gaia\MVC\Models\User;
use Gaia\MVC\Models\Workspace;

use function Gaia\Libraries\Utils\create_guid;

/**
 * Records the activities of a workspace (Workspaces D-019): Activity rows
 * with `relatedTo` `workspace`, in the same type/description shape as project
 * activities. A change of the workspace itself is `created` or `updated`; a
 * change of a project or a member in it is `related`, naming the module
 * (`project`, `membership`) and what happened to it.
 *
 * Recording is a side effect: a failure is logged and never fails the write
 * that caused it.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Workspaces
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkspaceActivity
{
    const RELATED_TO = 'workspace';

    /**
     * Fields whose change is recorded, with the label the description uses.
     * `runtimeId` is described as a binding instead.
     *
     * @var array
     */
    const UPDATED_FIELDS = [
        'name' => 'Name',
        'shortCode' => 'Short code',
        'description' => 'Description',
        'runtimeId' => null,
    ];

    /**
     * @param  object $workspace
     * @return void
     */
    public function created($workspace)
    {
        $this->safely(function () use ($workspace) {
            $this->record($workspace->id ?? null, 'created', 'Workspace <b>' . $this->escape($workspace->name ?? '') . '</b> created');
        });
    }

    /**
     * One row per recorded field the update changed.
     *
     * @param  object $workspace
     * @param  array  $audit field => ['old' => ..., 'new' => ...]
     * @return void
     */
    public function updated($workspace, $audit)
    {
        $this->safely(function () use ($workspace, $audit) {
            if (!is_array($audit)) {
                return;
            }

            foreach (self::UPDATED_FIELDS as $field => $label) {
                if (!isset($audit[$field]) || !is_array($audit[$field])) {
                    continue;
                }
                $old = (string) ($audit[$field]['old'] ?? '');
                $new = (string) ($audit[$field]['new'] ?? '');
                if ($old === $new) {
                    continue;
                }

                $description = $field === 'runtimeId'
                    ? $this->runtimeDescription($old, $new)
                    : $this->fieldDescription($field, $label, $old, $new);
                $this->record($workspace->id ?? null, 'updated', $description);
            }
        });
    }

    /**
     * A project was created in the workspace.
     *
     * @param  string $workspaceId
     * @param  object $project
     * @return void
     */
    public function projectAdded($workspaceId, $project)
    {
        $this->safely(function () use ($workspaceId, $project) {
            $this->recordRelated(
                $workspaceId,
                'project',
                'created',
                'Project <b>' . $this->escape($project->name ?? '') . '</b> added'
            );
        });
    }

    /**
     * A project moved from one workspace to another: the workspace it left
     * records `movedOut`, the one it joined `movedIn`. A legacy project
     * without a workspace only records the arrival.
     *
     * @param  object      $project
     * @param  string|null $fromWorkspaceId
     * @param  string      $toWorkspaceId
     * @return void
     */
    public function projectMoved($project, $fromWorkspaceId, $toWorkspaceId)
    {
        $this->safely(function () use ($project, $fromWorkspaceId, $toWorkspaceId) {
            $projectName = $this->escape($project->name ?? '');
            $fromName = $this->escape($this->workspaceName($fromWorkspaceId));
            $toName = $this->escape($this->workspaceName($toWorkspaceId));

            if (!$this->isBlank($fromWorkspaceId)) {
                $this->recordRelated(
                    $fromWorkspaceId,
                    'project',
                    'movedOut',
                    "Project <b>$projectName</b> moved to <b>$toName</b>"
                );
            }

            $description = $this->isBlank($fromWorkspaceId)
                ? "Project <b>$projectName</b> moved in"
                : "Project <b>$projectName</b> moved in from <b>$fromName</b>";
            $this->recordRelated($toWorkspaceId, 'project', 'movedIn', $description);
        });
    }

    /**
     * A person joined the workspace's projects (once per person, however many
     * projects the sync added them to).
     *
     * @param  string $workspaceId
     * @param  string $userId
     * @return void
     */
    public function memberAdded($workspaceId, $userId)
    {
        $this->safely(function () use ($workspaceId, $userId) {
            $this->recordRelated(
                $workspaceId,
                'membership',
                'created',
                'New member <b>' . $this->escape($this->userName($userId)) . '</b> has been added to the workspace'
            );
        });
    }

    /**
     * A person left the workspace's projects (once per person).
     *
     * @param  string $workspaceId
     * @param  string $userId
     * @return void
     */
    public function memberRemoved($workspaceId, $userId)
    {
        $this->safely(function () use ($workspaceId, $userId) {
            $this->recordRelated(
                $workspaceId,
                'membership',
                'deleted',
                'Member <b>' . $this->escape($this->userName($userId)) . '</b> has been removed from the workspace'
            );
        });
    }

    /**
     * @param  string $field
     * @param  string $label
     * @param  string $old
     * @param  string $new
     * @return string
     */
    protected function fieldDescription($field, $label, $old, $new)
    {
        if ($field === 'description') {
            return 'Description updated';
        }

        return "$label changed from <b>" . $this->escape($old) . '</b> to <b>' . $this->escape($new) . '</b>';
    }

    /**
     * @param  string $old runtime id before ('' for none)
     * @param  string $new runtime id after ('' for none)
     * @return string
     */
    protected function runtimeDescription($old, $new)
    {
        if ($old === '') {
            return 'Runtime <b>' . $this->escape($this->runtimeName($new)) . '</b> bound';
        }
        if ($new === '') {
            return 'Runtime <b>' . $this->escape($this->runtimeName($old)) . '</b> unbound';
        }

        return 'Runtime changed from <b>' . $this->escape($this->runtimeName($old))
            . '</b> to <b>' . $this->escape($this->runtimeName($new)) . '</b>';
    }

    /**
     * @param  string $workspaceId
     * @param  string $type
     * @param  string $description
     * @return void
     */
    protected function record($workspaceId, $type, $description)
    {
        if ($this->isBlank($workspaceId)) {
            return;
        }

        $this->write([
            'relatedTo' => self::RELATED_TO,
            'relatedId' => (string) $workspaceId,
            'type' => $type,
            'description' => $description,
        ]);
    }

    /**
     * @param  string $workspaceId
     * @param  string $module
     * @param  string $what
     * @param  string $description
     * @return void
     */
    protected function recordRelated($workspaceId, $module, $what, $description)
    {
        if ($this->isBlank($workspaceId)) {
            return;
        }

        $this->write([
            'relatedTo' => self::RELATED_TO,
            'relatedId' => (string) $workspaceId,
            'type' => 'related',
            'relatedActivityModule' => $module,
            'relatedActivity' => $what,
            'description' => $description,
        ]);
    }

    /**
     * Save one Activity row; a failure is logged, never thrown.
     *
     * @param  array $row
     * @return void
     */
    protected function write(array $row)
    {
        try {
            $activity = new Activity();
            $activity->id = create_guid();
            foreach ($row as $field => $value) {
                $activity->{$field} = $value;
            }
            if (!$activity->save()) {
                $this->logError('write() | ' . implode('; ', $activity->getMessages()));
            }
        } catch (\Throwable $e) {
            $this->logError('write() | ' . $e->getMessage());
        }
    }

    /**
     * Run one recording, including its name lookups; a failure is logged,
     * never thrown, so it can't fail the write that caused it.
     *
     * @param  callable $recording
     * @return void
     */
    protected function safely(callable $recording)
    {
        try {
            $recording();
        } catch (\Throwable $e) {
            $this->logError($e->getMessage());
        }
    }

    /**
     * @param  string $message
     * @return void
     */
    protected function logError($message)
    {
        global $logger;

        if ($logger) {
            $logger->error('WorkspaceActivity | ' . $message);
        }
    }

    /**
     * @param  string $userId
     * @return string
     */
    protected function userName($userId)
    {
        $user = User::findFirst(['conditions' => 'id = :id:', 'bind' => ['id' => (string) $userId]]);

        return $user ? (string) $user->name : '#' . $userId;
    }

    /**
     * @param  string|null $workspaceId
     * @return string
     */
    protected function workspaceName($workspaceId)
    {
        if ($this->isBlank($workspaceId)) {
            return '';
        }
        $workspace = Workspace::findFirst(['conditions' => 'id = :id:', 'bind' => ['id' => (string) $workspaceId]]);

        return $workspace ? (string) $workspace->name : '#' . $workspaceId;
    }

    /**
     * @param  string $runtimeId
     * @return string
     */
    protected function runtimeName($runtimeId)
    {
        $client = Serviceclient::findFirst(['conditions' => 'id = :id:', 'bind' => ['id' => (string) $runtimeId]]);

        return $client ? (string) $client->name : '#' . $runtimeId;
    }

    /**
     * @param  string $value
     * @return string
     */
    protected function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param  mixed $value
     * @return bool
     */
    private function isBlank($value)
    {
        return $value === null || (string) $value === '';
    }
}
