<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

use Phalcon\Cli\Task;
use Gaia\Services\NotificationRelatedEntityBackfill;
use Gaia\Services\NotificationRelatedEntityResolver;
use Gaia\Services\NotificationRelatedRecordFinder;

/**
 * Notification Task
 *
 * Maintenance actions for system notifications.
 *
 * @author   Agent Odysseus <agent.odysseus@projects4.me>
 * @package  Gaia\Core\Tasks
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NotificationTask extends Task
{
    /**
     * Default action - shows help
     */
    public function mainAction()
    {
        echo "Notification Task - Available actions:" . PHP_EOL;
        echo "  backfillRelated - Fill relatedTo/relatedId on notifications created before those columns existed" . PHP_EOL;
    }

    /**
     * Fill relatedTo/relatedId on notifications that lack them. Safe to run more than once.
     */
    public function backfillRelatedAction()
    {
        try {
            $db = $this->di->get('db');
            $backfill = new NotificationRelatedEntityBackfill(
                $db,
                new NotificationRelatedEntityResolver(new NotificationRelatedRecordFinder($db))
            );

            $counts = $backfill->run();

            echo "Notification related-entity backfill completed." . PHP_EOL;
            echo "  updated:    " . $counts['updated'] . PHP_EOL;
            echo "  skipped:    " . $counts['skipped'] . " (already set)" . PHP_EOL;
            echo "  unresolved: " . $counts['unresolved'] . " (left NULL)" . PHP_EOL;
        } catch (\Throwable $e) {
            echo "Backfill failed: " . $e->getMessage() . PHP_EOL;
            exit(1);
        }
    }
}
