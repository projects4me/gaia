<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models\Behaviors;

use Gaia\Libraries\Utils\TransactionScope;
use Gaia\Libraries\Workspaces\MembershipSync;
use Gaia\Libraries\Workspaces\ProjectMoveGate;
use Gaia\Libraries\Workspaces\WorkspaceActivity;
use Gaia\Libraries\Workspaces\ProjectWorkspace;
use Gaia\MVC\REST\Controllers\Components\Events\MembershipLiveEventsComponent;
use Phalcon\Messages\Message;
use Phalcon\Mvc\ModelInterface;
use Phalcon\Mvc\Model\BehaviorInterface;
use Phalcon\Mvc\Model\Behavior;

/**
 * Gives every new project a workspace (Workspaces D-002), gives a project
 * created into a workspace the workspace's members (D-004), and lets a project
 * move to another workspace only when nothing of the old one is still working
 * in it (D-011). It stamps when the project joined its workspace on create
 * and on a move, and keeps the stored value on any other save (D-016). The
 * rules live in {@see ProjectWorkspace}, {@see MembershipSync} and
 * {@see ProjectMoveGate}; the move's membership sync runs from the Project
 * model, in one transaction with the save.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Behavior
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class projectWorkspaceBehavior extends Behavior implements BehaviorInterface
{
    /**
     * Returning false from a before* event cancels the save.
     *
     * @param  string         $eventType
     * @param  ModelInterface $model
     * @return mixed
     */
    public function notify($eventType, ModelInterface $model)
    {
        if (method_exists($this, $eventType)) {
            return $this->$eventType($model);
        }
    }

    /**
     * Runs after the model's own validation, so no workspace is created for a
     * project that fails it.
     *
     * @param  ModelInterface $model
     * @return bool
     */
    protected function beforeCreate($model)
    {
        try {
            $workspace = new ProjectWorkspace();
            $model->workspaceId = $workspace
                ->resolveForNewProject(
                    $model->workspaceId ?? null,
                    $model->name ?? '',
                    $model->shortCode ?? null,
                    $model->projectManager ?? null
                );
            $model->workspaceJoinedAt = $workspace->joinedAt(null, $model->workspaceId, $this->now());
        } catch (\InvalidArgumentException $e) {
            $model->appendMessage(new Message($e->getMessage(), 'workspaceId', 'InvalidValue'));
            return false;
        }

        return true;
    }

    /**
     * Add the members of the workspace's other projects to the new project,
     * all or none, then publish their access change. The project itself is
     * already saved: a failed sync is logged and does not undo it.
     *
     * @param  ModelInterface $model
     * @return void
     */
    protected function afterCreate($model)
    {
        global $logger;

        (new WorkspaceActivity())->projectAdded($model->workspaceId ?? null, $model);

        $seed = function () use ($model) {
            return (new MembershipSync())->projectCreated($model->id ?? null, $model->workspaceId ?? null);
        };

        try {
            $connection = $model->getWriteConnection();
            if ($connection->isUnderTransaction()) {
                $added = $seed();
            } else {
                $added = (new TransactionScope($connection))->run($seed, function () {
                    return true;
                });
            }
        } catch (\Throwable $e) {
            if ($logger) {
                $logger->error('projectWorkspaceBehavior::afterCreate() | ' . $e->getMessage());
            }
            return;
        }

        if (!empty($added)) {
            (new MembershipLiveEventsComponent())->publishForMembers($added);
        }
    }

    /**
     * A save keeps the stored join date unless it changes the workspace
     * (D-016). A save that changes the workspace must pass the move gate: a
     * missing or unknown target is a message on workspaceId (422); a missing
     * workspace.update (403) or work still in progress (409) throws.
     *
     * @param  ModelInterface $model
     * @return bool
     * @throws \Gaia\Exception\Access
     * @throws \Gaia\Exception\Conflict
     */
    protected function beforeValidationOnUpdate($model)
    {
        $snapshot = $model->getSnapshotData();
        $workspace = new ProjectWorkspace();
        $model->workspaceJoinedAt = $workspace->joinedAt($snapshot, $model->workspaceId ?? null, $this->now());
        if (!$workspace->isMove($snapshot, $model->workspaceId ?? null)) {
            return true;
        }

        try {
            (new ProjectMoveGate())
                ->check($model->id ?? null, $snapshot['workspaceId'] ?? null, $model->workspaceId ?? null);
        } catch (\InvalidArgumentException $e) {
            $model->appendMessage(new Message($e->getMessage(), 'workspaceId', 'InvalidValue'));
            return false;
        }

        return true;
    }

    /**
     * The current UTC time, as stored in datetime columns.
     *
     * @return string
     */
    protected function now()
    {
        return gmdate('Y-m-d H:i:s');
    }
}
