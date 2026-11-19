<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Search;

use Gaia\Libraries\Meta\Manager;
use Gaia\MVC\REST\Controllers\Components\Events\SearchLiveEvents;

/**
 * The search reindex: pages through every non-deleted record of the searchable models with semantic fields,
 * stores the canonical semantic text and hash where they are missing or differ (a backfill, or a change to the
 * text rule), then announces search.semantic.changed, paced, for every record whose embedding is missing or was made
 * from another text. Hermes keeps no history, so this is how Ares catches up on the events it missed.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Libraries\Search
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SemanticReindexer
{
    /**
     * Records read per query.
     *
     * @var int
     */
    public const PAGE_SIZE = 200;

    /**
     * Pause after each published event, in microseconds.
     *
     * @var int
     */
    public const DEFAULT_PAUSE = 50000;

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
     * @var SearchLiveEvents
     */
    private $events;

    /**
     * @var callable
     */
    private $pause;

    /**
     * @param SearchableRegistry $registry
     * @param Manager $metaManager
     * @param SemanticRecords $records
     * @param SearchLiveEvents $events
     * @param callable|null $pause Called after each published event; null sleeps DEFAULT_PAUSE
     */
    public function __construct(
        SearchableRegistry $registry,
        Manager $metaManager,
        SemanticRecords $records,
        SearchLiveEvents $events,
        callable $pause = null
    ) {
        $this->registry = $registry;
        $this->metaManager = $metaManager;
        $this->records = $records;
        $this->events = $events;
        $this->pause = $pause ?: function () {
            usleep(self::DEFAULT_PAUSE);
        };
    }

    /**
     * This function reindexes one search type, or every type with semantic fields.
     *
     * @param string|null $type
     * @return array Counts per type: records, updated (text stored), skipped (saved meanwhile), published and failed
     *               (text not buildable or event not sent)
     * @throws \Gaia\Exception\Exception When the type is unknown or has no semantic fields
     */
    public function run($type = null)
    {
        $counts = array();
        foreach ($this->blocks($type) as $block) {
            $counts[$block['type']] = $this->reindex($block);
        }

        return $counts;
    }

    /**
     * This function returns the search blocks to reindex.
     *
     * @param string|null $type
     * @return array[]
     * @throws \Gaia\Exception\Exception
     */
    private function blocks($type)
    {
        $blocks = array();
        foreach ($this->registry->types() as $known) {
            $block = $this->registry->forType($known);
            if ($block['semantic'] !== array() && ($type === null || $type === $known)) {
                $blocks[] = $block;
            }
        }
        if ($type !== null && $blocks === array()) {
            throw new \Gaia\Exception\Exception(
                "Search type '{$type}' is unknown or has no semantic fields."
            );
        }

        return $blocks;
    }

    /**
     * This function reindexes the records of one searchable model.
     *
     * @param array $block A registry entry
     * @return array
     */
    private function reindex(array $block)
    {
        $table = $this->metaManager->getModelMeta($block['model'])['tableName'];
        $resource = strtolower($block['model']);
        $semanticText = SemanticText::forBlock($block);
        $counts = array('records' => 0, 'updated' => 0, 'skipped' => 0, 'published' => 0, 'failed' => 0);

        $afterId = '';
        do {
            $rows = $this->records->page($table, $block['semantic'], $afterId, self::PAGE_SIZE);
            foreach ($rows as $row) {
                $counts['records']++;
                $afterId = $row['id'];
                try {
                    $text = $semanticText->text($row);
                } catch (\Throwable $e) {
                    // A value the rule cannot read (e.g. past the PCRE limits) skips that record, not the run
                    $counts['failed']++;
                    continue;
                }
                $hash = SemanticText::hashOf($text);
                if ($row[SearchBlock::SEMANTIC_HASH] !== $hash) {
                    if (!$this->records->store($table, $row['id'], $text, $hash, $row[SearchBlock::SEMANTIC_HASH])) {
                        // Saved since the page was read: the save stored its text and announced it
                        $counts['skipped']++;
                        continue;
                    }
                    $counts['updated']++;
                }
                if ($row[SearchBlock::EMBEDDING_HASH] !== $hash) {
                    $published = $this->events->publishSemanticChanged($block['type'], $resource, $row['id']) !== null;
                    $counts[$published ? 'published' : 'failed']++;
                    ($this->pause)();
                }
            }
        } while (count($rows) === self::PAGE_SIZE);

        return $counts;
    }
}
