<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Libraries\Meta\Manager;

/**
 * The records the Ares service still has to embed, across every searchable model with semantic fields: non-deleted
 * records whose searchDesiredHash is set and whose searchActualHash is null or differs from it. Pages are ordered by
 * type, then id, and continue from a keyset cursor, since records leave the set as they are embedded.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PendingEmbeddings
{
    /**
     * @var SearchableRegistry
     */
    private $registry;

    /**
     * @var Manager
     */
    private $metaManager;

    /**
     * @var SemanticRecords
     */
    private $records;

    /**
     * @param SearchableRegistry $registry
     * @param Manager $metaManager
     * @param SemanticRecords $records
     */
    public function __construct(SearchableRegistry $registry, Manager $metaManager, SemanticRecords $records)
    {
        $this->registry = $registry;
        $this->metaManager = $metaManager;
        $this->records = $records;
    }

    /**
     * The pending list over every searchable model in the metadata, read from the shared connection.
     *
     * @param Manager $metaManager
     * @return self
     */
    public static function forMetadata(Manager $metaManager)
    {
        return new self(SearchableRegistry::fromMetadata($metaManager), $metaManager, new SemanticRecords());
    }

    /**
     * This function returns one page of pending records.
     *
     * @param PendingRequest $request
     * @return array {data: [{type, resource, id}], next: cursor or null when the set is exhausted}
     * @throws \Gaia\Exception\BadRequest When the cursor names a type without semantic fields
     */
    public function page(PendingRequest $request)
    {
        $blocks = $this->blocks();
        $afterType = $request->afterType();
        if ($afterType !== null && !isset($blocks[$afterType])) {
            throw new \Gaia\Exception\BadRequest('The cursor must be the next value of a previous page.');
        }

        $data = array();
        foreach ($blocks as $type => $block) {
            if ($afterType !== null && strcmp($type, $afterType) < 0) {
                continue;
            }
            $afterId = $type === $afterType ? $request->afterId() : '';
            $table = $this->metaManager->getModelMeta($block['model'])['tableName'];
            $ids = $this->records->pending($table, $afterId, $request->limit() - count($data));
            foreach ($ids as $id) {
                $data[] = array('type' => $type, 'resource' => strtolower($block['model']), 'id' => $id);
            }
            if (count($data) === $request->limit()) {
                break;
            }
        }

        $last = end($data);

        return array(
            'data' => $data,
            'next' => count($data) === $request->limit() ? PendingRequest::cursor($last['type'], $last['id']) : null,
        );
    }

    /**
     * The registry entries with semantic fields, keyed and ordered by type.
     *
     * @return array[]
     */
    private function blocks()
    {
        $blocks = array();
        foreach ($this->registry->types() as $type) {
            $block = $this->registry->forType($type);
            if ($block['semantic'] !== array()) {
                $blocks[$type] = $block;
            }
        }
        ksort($blocks, SORT_STRING);

        return $blocks;
    }
}
