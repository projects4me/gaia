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
    public $uses = ['Dashboard', 'GeneratePassword'];

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
     * user record.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        global $logger;

        $attributes = $this->extractRequestAttributes();
        $this->assertUserWritable(
            isset($attributes['id']) && $attributes['id'] !== '' ? $attributes['id'] : $this->id,
            $attributes
        );
        $this->assertAclLockoutSafeForAttributes($attributes);

        $response = parent::patchAction();

        $this->revokeSessionsIfDeactivated($attributes);

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
        foreach ($this->extractCollectionAttributes() as $attributes) {
            $this->assertUserWritable(isset($attributes['id']) ? $attributes['id'] : null, $attributes);
            $this->assertAclLockoutSafeForAttributes($attributes);
        }

        return parent::patchCollectionAction();
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
