<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

use Gaia\Libraries\Search\SearchableRegistry;
use Gaia\Libraries\Search\SemanticRecords;
use Gaia\Libraries\Search\SemanticReindexer;
use Gaia\MVC\REST\Controllers\Components\Events\SearchLiveEvents;
use Phalcon\Cli\Task;

/**
 * Search Task
 *
 * `php cli.php search reindex [type]` brings every searchable record's
 * semantic text and hash up to date and tells Ares about each record whose
 * embedding is missing or stale. Run it daily: Hermes keeps no history, so
 * this re-sends the changes Ares missed. Exits 1 when a record failed and 2
 * on an unknown type, so cron sees it.
 *
 * @author  Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Core\Tasks
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchTask extends Task
{
    /**
     * Default action - shows help
     */
    public function mainAction()
    {
        echo 'Search Task - Available actions:' . PHP_EOL;
        echo '  reindex [type] - Update the semantic text and re-publish records with a stale embedding' . PHP_EOL;
    }

    /**
     * Reindexes one search type, or every type with semantic fields.
     */
    public function reindexAction()
    {
        $params = $this->dispatcher->getParams();
        $type = isset($params[0]) ? (string) $params[0] : null;
        $metaManager = $this->getDI()->get('metaManager');
        $reindexer = new SemanticReindexer(
            SearchableRegistry::fromMetadata($metaManager),
            $metaManager,
            new SemanticRecords(),
            new SearchLiveEvents()
        );

        try {
            $result = $reindexer->run($type);
        } catch (\Gaia\Exception\Exception $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(2);
        }

        $failed = 0;
        foreach ($result as $searchType => $counts) {
            echo "{$searchType}: {$counts['records']} records, {$counts['updated']} texts updated, "
                . "{$counts['skipped']} saved meanwhile, {$counts['published']} published, {$counts['failed']} failed"
                . PHP_EOL;
            $failed += $counts['failed'];
        }
        if ($failed > 0) {
            exit(1);
        }
    }
}
