<?php

namespace Gaia\Libraries\Meta;

/**
 * Where a model's metadata array comes from: the metadata files, or a cache in front of them.
 *
 * @package  Foundation
 * @category metaManager
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
interface ModelMetadataSource
{
    /**
     * Returns the assembled metadata of a model (the ORM metadata keys plus fields, computed,
     * relationships, behaviors, acl, mentions and the search block).
     *
     * @param  string $model
     * @return array
     */
    public function get(string $model): array;
}
