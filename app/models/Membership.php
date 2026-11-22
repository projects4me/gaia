<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\Models;

use Gaia\Core\MVC\Models\Model;
use Gaia\Libraries\Utils\TransactionScope;
use Gaia\Libraries\Workspaces\MembershipSync;
use Gaia\Libraries\Workspaces\WorkspaceActivity;
use Gaia\Libraries\Workspaces\WorkspaceMemberships;
use Gaia\MVC\REST\Controllers\Components\Events\MembershipLiveEventsComponent;
use Phalcon\Messages\Message;

/**
 * Membership Model
 *
 * A membership created or deleted in one project of a workspace is created
 * or deleted in its other projects too (Workspaces D-004), in the same
 * transaction, whatever path wrote it (REST, workflows, the system).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Model
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Membership extends Model
{
    /**
     * The memberships the workspace sync wrote during the current save or
     * delete.
     *
     * @var Membership[]
     */
    protected $syncedMemberships = [];

    /**
     * @return bool
     * @throws \Throwable
     */
    public function save(): bool
    {
        return $this->writeInSync(function () {
            return parent::save();
        });
    }

    /**
     * @return bool
     * @throws \Throwable
     */
    public function delete(): bool
    {
        return $this->writeInSync(function () {
            return parent::delete();
        });
    }

    /**
     * Add the member to the workspace's other projects and record it on the
     * workspace once (Workspaces D-019).
     *
     * @return void
     */
    public function afterCreate()
    {
        $synced = MembershipSync::inRound();
        $this->syncedMemberships = (new MembershipSync())->created($this);
        if (!$synced) {
            (new WorkspaceActivity())->memberAdded($this->workspaceId(), (string) $this->userId);
        }
    }

    /**
     * Remove the member from the workspace's other projects and record it on
     * the workspace once (Workspaces D-019).
     *
     * @return void
     */
    public function afterDelete()
    {
        $synced = MembershipSync::inRound();
        $this->syncedMemberships = (new MembershipSync())->deleted($this);
        if (!$synced) {
            (new WorkspaceActivity())->memberRemoved($this->workspaceId(), (string) $this->userId);
        }
    }

    /**
     * The workspace of the membership's project, or null when it has none
     * (WorkspaceActivity then records nothing). Only a membership a user
     * made is recorded; the ones the sync writes in the other projects are
     * told apart by MembershipSync::inRound(), read before the sync runs.
     *
     * @return string|null
     */
    protected function workspaceId()
    {
        return (new WorkspaceMemberships())->workspaceIdOf($this->projectId ?? null);
    }

    /**
     * Run the write and the sync it triggers in one transaction (a write
     * inside a caller's transaction joins it), then publish the live events
     * of the synced rows. A failed sync fails the write.
     *
     * @param  callable $write
     * @return bool
     * @throws \Throwable
     */
    protected function writeInSync(callable $write)
    {
        $this->syncedMemberships = [];
        $connection = $this->getWriteConnection();

        // Inside a caller's transaction a failed sync throws, so the caller
        // rolls back the original write together with the synced rows.
        if ($connection->isUnderTransaction()) {
            $written = $write();
            $this->publishSynced($written);
            return $written;
        }

        try {
            $written = (new TransactionScope($connection))->run($write, function ($saved) {
                return $saved;
            });
        } catch (\PDOException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            $this->appendMessage(new Message($e->getMessage(), 'projectId', 'WorkspaceSync'));
            return false;
        }

        $this->publishSynced($written);

        return $written;
    }

    /**
     * The membership of a user in a project, if any.
     *
     * @param  string $projectId
     * @param  string $userId
     * @return Membership|null
     */
    public static function findOf($projectId, $userId)
    {
        $membership = self::findFirst([
            'conditions' => 'projectId = :projectId: AND userId = :userId:',
            'bind' => ['projectId' => (string) $projectId, 'userId' => (string) $userId],
        ]);

        return $membership ?: null;
    }

    /**
     * Publish user.access.changed for the users of the synced rows.
     *
     * @param  bool $written
     * @return void
     */
    protected function publishSynced($written)
    {
        if ($written && !empty($this->syncedMemberships)) {
            (new MembershipLiveEventsComponent())->publishForMembers($this->syncedMemberships);
        }
    }
}
