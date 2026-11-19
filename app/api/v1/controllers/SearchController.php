<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Search\SearchRequest;
use Gaia\Libraries\Search\SearchServiceFactory;
use Gaia\Libraries\Search\SemanticSearcher;

/**
 * Search Controller
 *
 * `GET /api/v1/search?types=issue,project&q=...&limit=20` searches the requested modules. The controller has no
 * permission of its own: the searched module's `.get` and record scope decide what the caller sees, inside
 * SearchService (see docs/architecture/search/decisions.md).
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchController extends RestController
{
    private const LIST_ONLY = 'Search is read as a list.';

    /**
     * Search publishes no action in the permission catalog.
     *
     * @var bool
     */
    protected $aclAllowed = false;

    /**
     * Only list is exposed.
     *
     * @var array $aclMap
     */
    protected $aclMap = array(
        'get' => array(
            'action' => 'get',
            'controllerAction' => 'getAction',
        ),
        'list' => array(
            'action' => 'get',
            'controllerAction' => 'listAction',
        ),
        'related' => array(
            'action' => 'get',
            'controllerAction' => 'relatedAction',
        ),
    );

    /**
     * The id and relation routes the router adds for every GET resource: search has no record to read.
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
     * Parse the query string into a SearchRequest, search and shape the results.
     *
     * @throws \Gaia\Exception\BadRequest When q, types or limit are missing or invalid
     * @throws \Gaia\Exception\Access When a requested type's .get is denied
     * @return \Phalcon\Http\Response
     */
    public function listAction()
    {
        $types = $this->request->get('types', null, '');
        $types = is_string($types)
            ? array_filter(array_map('trim', explode(',', $types)), 'strlen')
            : array('');
        $query = $this->request->get('q', null, '');
        $request = new SearchRequest(array_values($types), $query, $this->request->get('limit', null, null));

        $results = SearchServiceFactory::create($this->getDI())->search($request);

        $data = array();
        foreach ($results->items() as $result) {
            $data[] = array(
                'type' => 'searchresult',
                'id' => $result['type'] . ':' . $result['id'],
                'attributes' => array(
                    'module' => $result['type'],
                    'recordId' => $result['id'],
                    'title' => isset($result['title']) ? $result['title'] : null,
                    'snippet' => isset($result['snippet']) ? $result['snippet'] : null,
                    'score' => $result['score'],
                    'matchedBy' => $result['matchedBy'],
                    'pinned' => in_array('exact', (array) $result['matchedBy'], true),
                ),
            );
        }

        $this->response->setStatusCode(200, 'OK');
        $this->response->setJsonContent(array(
            'data' => $data,
            'meta' => array(
                'query' => $request->query(),
                'types' => $request->types(),
                'semantic' => $results->isUnavailable(SemanticSearcher::KIND) ? 'unavailable' : 'ok',
            ),
        ));

        return $this->response;
    }
}
