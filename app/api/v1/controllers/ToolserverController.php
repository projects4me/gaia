<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Tools\ToolCatalogRules;
use Gaia\Libraries\Tools\ToolRefreshException;
use Gaia\Libraries\Tools\ToolServerRefresher;
use Gaia\MVC\Models\Toolserver;

/**
 * Toolserver Controller
 *
 * Writes follow `ToolCatalogRules`. A PATCH with `"refresh": true` saves the
 * other fields, then reloads the server's tools with MCP tools/list (see
 * `ToolServerRefresher`); a server that cannot be listed answers 502 and
 * keeps its stored tools.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ToolserverController extends RestController
{
    /**
     * Create a tool server.
     *
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        if (isset($this->id)) {
            return $this->patchAction();
        }

        (new ToolCatalogRules())->assertServer($this->extractRequestAttributes());
        return parent::postAction();
    }

    /**
     * Update a tool server, and reload its tools when `refresh` is true.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        $attributes = $this->extractRequestAttributes();
        (new ToolCatalogRules())->assertServer($attributes);

        $refresh = isset($attributes['refresh']) && filter_var($attributes['refresh'], FILTER_VALIDATE_BOOLEAN);
        $serverId = isset($attributes['id']) && $attributes['id'] !== '' ? $attributes['id'] : $this->id;
        $refresher = new ToolServerRefresher();
        if ($refresh) {
            $refresher->assertRefreshable($refresher->findServer($serverId, $attributes));
        }

        // The other fields are saved first; a failed refresh (502) keeps them.
        $response = parent::patchAction();
        if (!$refresh || (int) $response->getStatusCode() !== 200) {
            return $response;
        }

        try {
            $refresher->refresh($refresher->findServer($serverId));
        } catch (ToolRefreshException $e) {
            $this->response->setStatusCode(502, 'Bad Gateway');
            $this->response->setJsonContent([
                'status' => 'ERROR',
                'messages' => [$e->getMessage()],
            ]);
            return $this->response;
        }

        $data = (new Toolserver())->read(['id' => $serverId]);
        $dataArray = $this->extractData($data, [], false, 'one');
        return $this->returnResponse($this->buildHAL($dataArray));
    }

    /**
     * Update several tool servers, each checked like a single update. A
     * collection update never refreshes.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchCollectionAction()
    {
        $rules = new ToolCatalogRules();
        foreach ($this->extractCollectionAttributes() as $attributes) {
            $rules->assertServer($attributes);
        }
        return parent::patchCollectionAction();
    }

}
