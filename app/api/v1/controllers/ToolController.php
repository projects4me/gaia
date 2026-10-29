<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Tools\ToolCatalogRules;

/**
 * Tool Controller
 *
 * Tools of an MCP server are normally written by a refresh (PATCH
 * toolserver with "refresh": true); runtime built-ins such as Read or Bash
 * are written here. Writes follow `ToolCatalogRules`.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Foundation
 * @category Controller
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ToolController extends RestController
{
    /**
     * Create a tool.
     *
     * @return \Phalcon\Http\Response
     */
    public function postAction()
    {
        if (isset($this->id)) {
            return $this->patchAction();
        }

        (new ToolCatalogRules())->assertTool($this->extractRequestAttributes());
        return parent::postAction();
    }

    /**
     * Update a tool.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        (new ToolCatalogRules())->assertTool($this->extractRequestAttributes());
        return parent::patchAction();
    }

    /**
     * Update several tools, each checked like a single update.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchCollectionAction()
    {
        $rules = new ToolCatalogRules();
        foreach ($this->extractCollectionAttributes() as $attributes) {
            $rules->assertTool($attributes);
        }
        return parent::patchCollectionAction();
    }
}
