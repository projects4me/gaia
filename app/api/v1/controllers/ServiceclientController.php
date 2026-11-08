<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Authorization\ServiceClientSecrets;
use Gaia\Libraries\Security\ServiceAcl;

/**
 * Serviceclient Controller
 *
 * Service clients are created by enrollment, not through the API (the route
 * allows only GET and PATCH). A PATCH changes one client. An admin's may carry
 * only `status`; `serviceClientBehavior` checks the value and cuts a disabled
 * client's tokens off. A service's own may carry only `rotateSecret: true`,
 * on its own record: it gets a new secret once, and its previous secret stays
 * valid for a grace period (D-068).
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
     * Error for a rotation PATCH that changes anything else.
     */
    public const ROTATE_ONLY_ERROR = 'rotateSecret must be sent alone and set to true.';

    /**
     * Error for a rotation asked for by a user.
     */
    public const ROTATE_BY_SERVICE_ERROR = 'A service client secret is rotated only by the service itself.';

    /**
     * The attribute that asks for a new secret.
     */
    public const ROTATE_FIELD = 'rotateSecret';

    /**
     * Attributes a PATCH may carry.
     */
    private const PATCHABLE = ['id', 'status'];

    /**
     * Attributes a rotation PATCH carries.
     */
    private const ROTATABLE = ['id', self::ROTATE_FIELD];

    /**
     * Change the client's status (a user) or rotate its secret (the service
     * itself, with its own service token; D-068).
     *
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\BadRequest
     */
    public function patchAction()
    {
        $attributes = $this->extractRequestAttributes();
        if (array_key_exists(self::ROTATE_FIELD, $attributes)) {
            return $this->rotateSecret($attributes);
        }

        if ($this->isServiceRequest()) {
            throw new \Gaia\Exception\UnAuthorized(ServiceAcl::ACCESS_DENIED_ERROR);
        }

        if (array_diff(array_keys($attributes), self::PATCHABLE)) {
            throw new \Gaia\Exception\BadRequest(self::STATUS_ONLY_ERROR);
        }

        $this->assertIdMatches($attributes);

        return parent::patchAction();
    }

    /**
     * Give the calling service a new secret; the response carries it once.
     * The service ACL has already held the token to `serviceclient.rotate`,
     * and `authorizeRecordUpdate()` holds the record to the service's own.
     *
     * @param  array $attributes
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\BadRequest
     * @throws \Gaia\Exception\Access When a user asks for it
     * @throws \Gaia\Exception\UnAuthorized
     */
    protected function rotateSecret(array $attributes)
    {
        if (array_diff(array_keys($attributes), self::ROTATABLE) || $attributes[self::ROTATE_FIELD] !== true) {
            throw new \Gaia\Exception\BadRequest(self::ROTATE_ONLY_ERROR);
        }
        $this->assertIdMatches($attributes);

        if (!$this->isServiceRequest()) {
            throw new \Gaia\Exception\Access(self::ROTATE_BY_SERVICE_ERROR);
        }

        $authorized = $this->authorizeRecordUpdate($attributes);
        if (!array_key_exists(self::ROTATE_FIELD, $authorized)) {
            throw new \Gaia\Exception\UnAuthorized(ServiceAcl::ACCESS_DENIED_ERROR);
        }

        $rotated = (new ServiceClientSecrets())->rotateServiceClient($attributes['id']);

        $this->response->setStatusCode(200, 'OK');
        return $this->returnResponse($this->buildHAL([
            'data' => [
                'type' => 'serviceclient',
                'id' => (string) $attributes['id'],
                'attributes' => [
                    'clientId' => $rotated['clientId'],
                    'clientSecret' => $rotated['secret'],
                    'previousSecretExpiresAt' => $rotated['previousSecretExpiresAt'],
                ],
            ],
        ]));
    }

    /**
     * Whether the request came with a service token (no current user).
     *
     * @return bool
     */
    protected function isServiceRequest()
    {
        global $currentUser;

        return !isset($currentUser->id);
    }

    /**
     * The PATCH names the client in its body, and the same one as the URL.
     *
     * @param  array $attributes
     * @return void
     * @throws \Gaia\Exception\BadRequest
     */
    private function assertIdMatches(array $attributes)
    {
        $id = isset($attributes['id']) ? (string) $attributes['id'] : '';
        if ($id === '' || (isset($this->id) && (string) $this->id !== $id)) {
            throw new \Gaia\Exception\BadRequest(self::ID_REQUIRED_ERROR);
        }
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
