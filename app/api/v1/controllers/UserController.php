<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Agents\AgentLifecycle;
use Gaia\Libraries\Agents\AgentUserPolicy;
use Gaia\Libraries\Security\AclLockoutGuard;
use Gaia\Libraries\Security\AgentEscalationGuard;
use Gaia\MVC\Models\Oauthaccesstoken;
use Gaia\MVC\Models\Oauthrefreshtoken;
use Gaia\MVC\Models\User;

/**
 * User controller
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class UserController extends RestController
{
    public $uses = ['Dashboard', 'GeneratePassword', 'Events\\UserAccessLiveEvents'];

    /**
     * Users created here are Humans; agents are created through the agent
     * endpoint (see `AgentUserPolicy`).
     *
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        if (!isset($this->id)) {
            (new AgentUserPolicy())->assertCreatable($this->extractRequestAttributes());
        } else {
            $this->assertUserWritable($this->id, $this->extractRequestAttributes());
        }

        return parent::postAction();
    }

    /**
     * This function updates user record and move user's profile from temp directory to its original
     * directory from where we deliver images to client side.
     *
     * Rejects deactivating the last usable member of the last admin-capable
     * role (see `AclLockoutGuard`). Soft-disables by revoking OAuth sessions
     * when `accountStatus` becomes non-authenticatable. Rejects changing a
     * user's type, a password on an agent user, and an agent writing its own
     * user record. When the saved `accountStatus` changed, fires
     * `rest:afterAccessChange` after the session revocation, so Hermes
     * hears user.access.changed once the tokens are gone.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        global $logger;

        $attributes = $this->extractRequestAttributes();
        $userId = isset($attributes['id']) && $attributes['id'] !== '' ? $attributes['id'] : $this->id;
        $this->assertUserWritable($userId, $attributes);
        $this->assertAclLockoutSafeForAttributes($attributes);
        $statusBefore = $this->accountStatusesBefore(array($userId => $attributes));

        $response = parent::patchAction();

        $this->revokeSessionsIfDeactivated($attributes);
        if ($this->succeeded($response)) {
            $this->fireAccessChanges($statusBefore);
        }

        $tempImagePath = APP_PATH . DS . 'filesystem' . DS . 'tmp' . DS . 'img' . DS . 'user' . DS . $this->id;
        //Check if user selected image exists in temp directory. If exists then move it to filesystem/img/user/ directory
        if (file_exists($tempImagePath)) {
            $targetPath = APP_PATH . DS . 'filesystem' . DS . 'img' . DS . 'user' . DS . $this->id;

            if (!rename($tempImagePath, $targetPath)) {
                $logger->error(
                    'Unable to move the file over to the filesystem/img/user directory, please make' .
                    ' sure that the directory exists and that its writable'
                );
                $this->response->setStatusCode(500, "Internal Server Error");
            }
        }
        return $response;
    }

    /**
     * Apply the user write rules to every record of a collection patch.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchCollectionAction()
    {
        // Unlike patchAction, this path does not revoke sessions, so a
        // deactivation here publishes user.access.changed while the
        // user's tokens are still valid.
        $byUser = array();
        foreach ($this->extractCollectionAttributes() as $attributes) {
            $this->assertUserWritable(isset($attributes['id']) ? $attributes['id'] : null, $attributes);
            $this->assertAclLockoutSafeForAttributes($attributes);
            if (isset($attributes['id'])) {
                $byUser[$attributes['id']] = $attributes;
            }
        }
        $statusBefore = $this->accountStatusesBefore($byUser);

        $response = parent::patchCollectionAction();
        if ($this->succeeded($response)) {
            $this->fireAccessChanges($statusBefore);
        }

        return $response;
    }

    /**
     * Reject deleting the last usable member of the last admin-capable role
     * (see `AclLockoutGuard`). Deleting an agent user deletes its agents row
     * in the same transaction.
     *
     * @method deleteAction
     * @throws \Gaia\Exception\Permission
     * @return \Phalcon\Http\Response
     */
    public function deleteAction()
    {
        global $currentUser;

        AgentEscalationGuard::assertNotOwnUser($currentUser, $this->id);
        $this->assertUserRemainsAdministrable($this->id);

        $lifecycle = new AgentLifecycle();
        return $lifecycle->transactional(function () use ($lifecycle) {
            $response = parent::deleteAction();
            if ((int) $response->getStatusCode() === 200) {
                $lifecycle->deleteAgentOfUser($this->id);
            }
            return $response;
        });
    }

    /**
     * The checks every user write passes: an agent cannot write its own user
     * record, and the type and agent password rules of `AgentUserPolicy`.
     *
     * @param  string|null $userId
     * @param  array       $attributes
     * @throws \Gaia\Exception\Access
     * @throws \Gaia\Exception\Permission
     * @return void
     */
    private function assertUserWritable($userId, array $attributes)
    {
        global $currentUser;

        AgentEscalationGuard::assertNotOwnUser($currentUser, $userId);

        $user = $userId ? User::findFirstById((string) $userId) : null;
        if ($user) {
            (new AgentUserPolicy())->assertUpdatable($user, $attributes);
        }
    }

    /**
     * If the patch would set a non-usable `accountStatus`, ensure another
     * usable admin path remains.
     *
     * @param  array $attributes
     * @throws \Gaia\Exception\Permission
     * @return void
     */
    private function assertAclLockoutSafeForAttributes(array $attributes)
    {
        if (!array_key_exists('accountStatus', $attributes)) {
            return;
        }

        if (AclLockoutGuard::isUsableAccountStatus($attributes['accountStatus'])) {
            return;
        }

        $userId = isset($attributes['id']) && $attributes['id'] !== ''
            ? $attributes['id']
            : $this->id;

        $this->assertUserRemainsAdministrable($userId);
    }

    /**
     * After a successful deactivate, revoke OAuth tokens and expire the session.
     *
     * @param  array $attributes
     * @return void
     */
    private function revokeSessionsIfDeactivated(array $attributes)
    {
        if (!array_key_exists('accountStatus', $attributes)) {
            return;
        }

        if (AclLockoutGuard::isAuthenticatableAccountStatus($attributes['accountStatus'])) {
            return;
        }

        $userId = isset($attributes['id']) && $attributes['id'] !== ''
            ? $attributes['id']
            : $this->id;

        if (!$userId) {
            return;
        }

        $user = User::findFirstById($userId);
        if (!$user || !$user->email) {
            return;
        }

        $this->deleteOAuthTokensForEmail($user->email);

        $user->sessionExpires = gmdate('Y-m-d H:i:s', time() - 1);
        $user->save();
    }

    /**
     * The saved `accountStatus` of each user whose patch sets one.
     *
     * @param  array $attributesByUser Patch attributes keyed by user id
     * @return array                   accountStatus keyed by user id
     */
    private function accountStatusesBefore(array $attributesByUser)
    {
        $statuses = array();
        foreach ($attributesByUser as $userId => $attributes) {
            if (!$userId || !is_array($attributes) || !array_key_exists('accountStatus', $attributes)) {
                continue;
            }
            $user = User::findFirstById((string) $userId);
            if ($user) {
                $statuses[(string) $userId] = $user->accountStatus;
            }
        }
        return $statuses;
    }

    /**
     * Fire `rest:afterAccessChange` for each user whose saved
     * `accountStatus` differs from before the patch, so the live events
     * component tells Hermes (user.access.changed).
     *
     * @param  array $statusBefore accountStatus keyed by user id
     * @return void
     */
    private function fireAccessChanges(array $statusBefore)
    {
        foreach ($statusBefore as $userId => $before) {
            $user = User::findFirstById((string) $userId);
            if ($user && (string) $user->accountStatus !== (string) $before) {
                $this->eventsManager->fire('rest:afterAccessChange', $this, (string) $userId);
            }
        }
    }

    /**
     * Whether a write response is a success (an unset status is a success).
     *
     * @param  \Phalcon\Http\ResponseInterface $response
     * @return bool
     */
    private function succeeded($response)
    {
        $code = (int) $response->getStatusCode();
        return $code === 0 || ($code >= 200 && $code < 300);
    }

    /**
     * Delete access and refresh tokens whose `user_id` is the user's email.
     *
     * @param  string $email
     * @return void
     */
    private function deleteOAuthTokensForEmail($email)
    {
        $accessTokens = Oauthaccesstoken::find([
            'conditions' => 'user_id = :userId:',
            'bind' => ['userId' => $email],
        ]);
        foreach ($accessTokens as $token) {
            $token->delete();
        }

        $refreshTokens = Oauthrefreshtoken::find([
            'conditions' => 'user_id = :userId:',
            'bind' => ['userId' => $email],
        ]);
        foreach ($refreshTokens as $token) {
            $token->delete();
        }
    }

    /**
     * @param  string $userId
     * @throws \Gaia\Exception\Permission
     * @return void
     */
    private function assertUserRemainsAdministrable($userId)
    {
        if (!$userId) {
            return;
        }

        if (!AclLockoutGuard::systemRetainsAdminPath(['excludeUserId' => $userId])) {
            throw new \Gaia\Exception\Permission(
                "This user cannot be deactivated or deleted: they are the last active member of the last role able to administer permissions, roles, role assignments, and users.",
                null,
                null,
                [
                    'suggestion' => 'Assign another active user to a role with full permission, role, userrole, and user write access before deactivating or deleting this user.'
                ]
            );
        }
    }
}
