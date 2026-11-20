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
 * embedding is missing or stale; it is run by hand. `php cli.php search
 * backfill [type]` is its text step alone and publishes nothing; the
 * container runs it on start. Ares finds the records it still has to embed
 * through GET /api/v1/searchpending. Both exit 1 when a record failed and 2
 * on an unknown type.
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
        echo '  backfill [type] - Store missing or outdated semantic text and hashes; publishes nothing' . PHP_EOL;
    }

    /**
     * Reindexes one search type, or every type with semantic fields.
     */
    public function reindexAction()
    {
        $this->report(function (SemanticReindexer $reindexer, $type) {
            return $reindexer->run($type);
        }, true);
    }

    /**
     * Backfills the semantic text and hash of one search type, or every type with semantic fields. The container
     * runs it on start, after the migrations.
     */
    public function backfillAction()
    {
        $this->report(function (SemanticReindexer $reindexer, $type) {
            return $reindexer->backfill($type);
        }, false);
    }

    /**
     * Runs one reindexer step for the type given on the command line and prints its counts per type.
     *
     * @param callable $step Takes the reindexer and the type, returns the counts per type
     * @param bool $publishes Whether the step publishes, so its counts have a published count
     */
    private function report(callable $step, $publishes)
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
            $result = $step($reindexer, $type);
        } catch (\Gaia\Exception\Exception $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(2);
        }

        $failed = 0;
        foreach ($result as $searchType => $counts) {
            echo "{$searchType}: {$counts['records']} records, {$counts['updated']} texts updated, "
                . "{$counts['skipped']} saved meanwhile, "
                . ($publishes ? "{$counts['published']} published, " : '')
                . "{$counts['failed']} failed" . PHP_EOL;
            $failed += $counts['failed'];
        }
        if ($failed > 0) {
            exit(1);
        }
    }
}
