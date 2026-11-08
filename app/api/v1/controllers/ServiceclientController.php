<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * Serviceclient Controller
 *
 * Service clients are created by enrollment, not through the API (the route
 * allows only GET and PATCH). A PATCH changes one client and may carry only
 * `status`; `serviceClientBehavior` checks the value and cuts a disabled
 * client's tokens off.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceclientController extends RestController
{
    /**
     * Error for a PATCH that changes anything but the status.
     */
    public const STATUS_ONLY_ERROR = 'Only the status of a service client can be changed.';

    /**
     * Error for a PATCH without the client's id.
     */
    public const ID_REQUIRED_ERROR = 'The service client id is required.';

    /**
     * Attributes a PATCH may carry.
     */
    private const PATCHABLE = ['id', 'status'];

    /**
     * Change the client's status.
     *
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\BadRequest
     */
    public function patchAction()
    {
        $attributes = $this->extractRequestAttributes();
        if (array_diff(array_keys($attributes), self::PATCHABLE)) {
            throw new \Gaia\Exception\BadRequest(self::STATUS_ONLY_ERROR);
        }

        $id = isset($attributes['id']) ? (string) $attributes['id'] : '';
        if ($id === '' || (isset($this->id) && (string) $this->id !== $id)) {
            throw new \Gaia\Exception\BadRequest(self::ID_REQUIRED_ERROR);
        }

        return parent::patchAction();
    }

    /**
     * Clients are changed one at a time.
     *
     * @throws \Gaia\Exception\BadRequest
     */
    public function patchCollectionAction()
    {
        throw new \Gaia\Exception\BadRequest(self::ID_REQUIRED_ERROR);
    }
}
