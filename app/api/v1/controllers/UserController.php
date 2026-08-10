<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Security\AclLockoutGuard;
use Gaia\Libraries\Utils\Util;

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
     * This function updates user record and move user's profile from temp directory to its original
     * directory from where we deliver images to client side.
     *
     * Rejects deactivating the last usable member of the last admin-capable
     * role (see `AclLockoutGuard`).
     *
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        global $logger;

        $this->assertAclLockoutSafeForPatch();

        $response = parent::patchAction();

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
     * Reject deleting the last usable member of the last admin-capable role
     * (see `AclLockoutGuard`).
     *
     * @method deleteAction
     * @throws \Gaia\Exception\Permission
     * @return \Phalcon\Http\Response
     */
    public function deleteAction()
    {
        $this->assertUserRemainsAdministrable($this->id);

        return parent::deleteAction();
    }

    /**
     * If the patch would set a non-usable `accountStatus`, ensure another
     * usable admin path remains.
     *
     * @throws \Gaia\Exception\Permission
     * @return void
     */
    private function assertAclLockoutSafeForPatch()
    {
        $attributes = $this->extractPatchAttributes();
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

    /**
     * Pull the first record attributes from a JSON:API or flat PATCH body.
     *
     * Mirrors the shape handling in `RestController::patchAction()`.
     *
     * @return array
     */
    private function extractPatchAttributes()
    {
        $util = new Util();
        $temp = $util->objectToArray($this->request->getJsonRawBody());
        if (!is_array($temp)) {
            return [];
        }

        if ($util->existSubArray($temp)) {
            if (isset($temp['data']['attributes']) && is_array($temp['data']['attributes'])) {
                $attributes = $temp['data']['attributes'];
                if (isset($temp['data']['id']) && $temp['data']['id'] !== '') {
                    $attributes['id'] = $temp['data']['id'];
                }
                return $attributes;
            }

            if (isset($temp[0]) && is_array($temp[0])) {
                return $temp[0];
            }

            return $temp;
        }

        return $temp;
    }
}
