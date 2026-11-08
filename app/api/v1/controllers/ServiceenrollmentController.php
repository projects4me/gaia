<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Authorization\ServiceEnrollments;

/**
 * Serviceenrollment Controller
 *
 * A service enrolls itself without a token: POST makes a request and returns
 * its user code and poll token, and GET by id with `?pollToken=` reads it and,
 * once an admin has approved it, returns the client id and secret, once
 * (`ServiceEnrollments`). Those two actions are anonymous; listing, related
 * reads and PATCH are authorized like any other admin resource. A PATCH
 * changes one request and may carry only `status` (approved or denied, checked
 * by `serviceEnrollmentBehavior`). See rbac decision D-066.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceenrollmentController extends RestController
{
    /**
     * Error for a PATCH that changes anything but the status.
     */
    public const STATUS_ONLY_ERROR = 'Only the status of an enrollment request can be changed.';

    /**
     * Error for a PATCH without the request's id.
     */
    public const ID_REQUIRED_ERROR = 'The enrollment request id is required.';

    /**
     * Error for a POST to an existing request.
     */
    public const POST_TO_ID_ERROR = 'An enrollment request is changed with PATCH.';

    /**
     * The resource type of the documents this controller builds itself.
     */
    private const TYPE = 'serviceenrollment';

    /**
     * Attributes a PATCH may carry.
     */
    private const PATCHABLE = ['id', 'status'];

    /**
     * Only making a request and polling it need no token.
     *
     * @var array
     */
    protected $anonymousActions = array('post', 'get');

    /**
     * Make an enrollment request.
     *
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\BadRequest
     * @throws \Gaia\Exception\TooManyRequests
     */
    public function postAction()
    {
        if (isset($this->id)) {
            throw new \Gaia\Exception\BadRequest(self::POST_TO_ID_ERROR);
        }

        $attributes = $this->extractRequestAttributes();
        $requested = $this->enrollments()->request(
            isset($attributes['name']) ? $attributes['name'] : null,
            isset($attributes['kind']) ? $attributes['kind'] : null,
            (string) $this->request->getClientAddress()
        );

        $document = $this->document($requested['enrollment']);
        $document['data']['attributes']['pollToken'] = $requested['pollToken'];

        $this->response->setStatusCode(201, "Created");
        return $this->returnResponse($document);
    }

    /**
     * Poll an enrollment request with its poll token.
     *
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\ResourceNotFound
     */
    public function getAction()
    {
        $polled = $this->enrollments()->poll(
            isset($this->id) ? (string) $this->id : '',
            $this->request->getQuery('pollToken', null, null)
        );

        $document = $this->document($polled['enrollment']);
        if ($polled['secret'] !== null) {
            $document['data']['attributes']['clientId'] = $polled['clientId'];
            $document['data']['attributes']['clientSecret'] = $polled['secret'];
        }

        $this->response->setStatusCode(200, "OK");
        return $this->returnResponse($document);
    }

    /**
     * Approve or deny a request.
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
     * Requests are decided one at a time.
     *
     * @throws \Gaia\Exception\BadRequest
     */
    public function patchCollectionAction()
    {
        throw new \Gaia\Exception\BadRequest(self::ID_REQUIRED_ERROR);
    }

    /**
     * The service's view of its request, as a JSON:API resource: never the
     * poll token hash, the requester's address or who decided.
     *
     * @param  \Gaia\MVC\Models\Serviceenrollment $enrollment
     * @return array
     */
    private function document($enrollment)
    {
        return [
            'data' => [
                'type' => self::TYPE,
                'id' => (string) $enrollment->id,
                'attributes' => [
                    'name' => (string) $enrollment->name,
                    'kind' => (string) $enrollment->kind,
                    'status' => (string) $enrollment->status,
                    'userCode' => (string) $enrollment->userCode,
                    'expiresAt' => (string) $enrollment->expiresAt,
                ],
            ],
            // The link leaves the query string out, so a poll token is never echoed.
            'meta' => [
                'count' => 1,
                'links' => ['self' => ['href' => '/api/v1/' . self::TYPE . '/' . $enrollment->id]],
            ],
        ];
    }

    /**
     * @return ServiceEnrollments
     */
    private function enrollments()
    {
        return new ServiceEnrollments();
    }
}
