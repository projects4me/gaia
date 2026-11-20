<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Authorization\ServiceClients;
use Gaia\Libraries\Search\PendingEmbeddings;
use Gaia\Libraries\Search\PendingRequest;
use Gaia\Libraries\Security\BearerToken;
use Gaia\Libraries\Security\PendingEmbeddingsCallerGuard;

/**
 * Searchpending Controller
 *
 * `GET /api/v1/searchpending?after=<cursor>&limit=<n>` lists the records the Ares service still has to embed, as ids
 * only. The caller is the Ares service alone, checked by PendingEmbeddingsCallerGuard, not a user's role (see
 * docs/architecture/search/decisions.md, D-009).
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchpendingController extends RestController
{
    private const LIST_ONLY = 'The pending records are read as a list.';

    /**
     * The caller is a service, checked by the guard, not a user.
     *
     * @var bool
     */
    protected $authorization = false;

    /**
     * The id and relation routes the router adds for every GET resource: there is no record to read.
     *
     * @throws \Gaia\Exception\ResourceNotFound
     * @return void
     */
    public function getAction()
    {
        throw new \Gaia\Exception\ResourceNotFound(self::LIST_ONLY);
    }

    /**
     * @throws \Gaia\Exception\ResourceNotFound
     * @return void
     */
    public function relatedAction()
    {
        throw new \Gaia\Exception\ResourceNotFound(self::LIST_ONLY);
    }

    /**
     * Check the caller, parse the cursor and limit, and return one page.
     *
     * @throws \Gaia\Exception\UnAuthorized When there is no live token
     * @throws \Gaia\Exception\Access When the caller is not the Ares service
     * @throws \Gaia\Exception\BadRequest When the cursor or the limit is malformed
     * @return \Phalcon\Http\Response
     */
    public function listAction()
    {
        // The header is read the way the OAuth server reads it: Apache may hide it from the request
        $client = (new PendingEmbeddingsCallerGuard())->assertCaller(
            BearerToken::fromHeader($this->getOAuthRequest()->headers('AUTHORIZATION'))
        );
        // authorization is off here, so the client's use is stamped as authorize() would
        (new ServiceClients())->recordUse($client->clientId);

        $request = new PendingRequest($this->request->get('after', null, null), $this->request->get('limit', null, null));
        $page = PendingEmbeddings::forMetadata($this->getDI()->get('metaManager'))->page($request);

        $this->response->setStatusCode(200, 'OK');
        $this->response->setHeader('Cache-Control', 'no-store');
        $this->response->setJsonContent($page);

        return $this->response;
    }
}
