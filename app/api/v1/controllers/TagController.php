<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\Libraries\Tags\TagMergeRefusal;
use Gaia\Libraries\Tags\TagMerger;

/**
 * Tag controller
 *
 * A PATCH with `"mergeInto": "<tagId>"` merges the tag into that tag of the
 * same project (see `TagMerger`, Tags D-003) and answers with the target
 * tag; the body's other fields are not saved.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TagController extends RestController
{
    /**
     * Update a tag, or merge it into another when `mergeInto` is given.
     *
     * @return \Phalcon\Http\Response
     */
    public function patchAction()
    {
        $attributes = $this->extractRequestAttributes();
        if (!array_key_exists('mergeInto', $attributes)) {
            return parent::patchAction();
        }

        $sourceId = isset($attributes['id']) && is_scalar($attributes['id']) && $attributes['id'] !== ''
            ? (string) $attributes['id']
            : (string) $this->id;
        $this->id = $sourceId;
        // The target must be in the source's project, so the source's scope covers both.
        $this->authorizeRecordUpdate(['id' => $sourceId]);

        try {
            $target = (new TagMerger())->merge($sourceId, is_scalar($attributes['mergeInto']) ? (string) $attributes['mergeInto'] : '');
        } catch (TagMergeRefusal $refusal) {
            $this->response->setStatusCode($refusal->getCode());
            $this->response->setJsonContent(['status' => 'ERROR', 'messages' => [$refusal->getMessage()]]);
            return $this->response;
        }

        $this->id = (string) $target->id;
        return $this->getAction();
    }
}
