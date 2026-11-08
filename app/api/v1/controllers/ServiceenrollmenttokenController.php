<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Authorization\ServiceEnrollmentTokens;

/**
 * Serviceenrollmenttoken Controller
 *
 * An admin issues a single-use enrollment token with POST (kind, name); the
 * response carries the token once, and only its hash is stored
 * (`ServiceEnrollmentTokens`). Listing and DELETE (revoke) are the standard
 * actions. The route allows no PUT or PATCH: a token never changes except
 * when it is spent. See rbac decision D-070.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ServiceenrollmenttokenController extends RestController
{
    /**
     * Error for a POST to an existing token.
     */
    public const POST_TO_ID_ERROR = 'An enrollment token is issued with POST to the collection.';

    /**
     * The resource type of the document this controller builds itself.
     */
    private const TYPE = 'serviceenrollmenttoken';

    /**
     * Issue a token and return it once.
     *
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\BadRequest
     */
    public function postAction()
    {
        if (isset($this->id)) {
            throw new \Gaia\Exception\BadRequest(self::POST_TO_ID_ERROR);
        }

        $attributes = $this->extractRequestAttributes();
        $issued = (new ServiceEnrollmentTokens())->issue(
            isset($attributes['name']) ? $attributes['name'] : null,
            isset($attributes['kind']) ? $attributes['kind'] : null
        );
        $token = $issued['token'];

        $document = [
            'data' => [
                'type' => self::TYPE,
                'id' => (string) $token->id,
                'attributes' => [
                    'name' => (string) $token->name,
                    'kind' => (string) $token->kind,
                    'expiresAt' => (string) $token->expiresAt,
                    'token' => $issued['secret'],
                ],
            ],
            'meta' => [
                'count' => 1,
                'links' => ['self' => ['href' => '/api/v1/' . self::TYPE . '/' . $token->id]],
            ],
        ];

        $this->response->setStatusCode(201, "Created");
        return $this->returnResponse($document);
    }
}
