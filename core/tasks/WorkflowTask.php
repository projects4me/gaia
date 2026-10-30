<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

use Gaia\Libraries\Workflows\ScheduleTriggerRunner;
use Gaia\Libraries\Workflows\WorkflowRunner;
use Gaia\MVC\REST\Controllers\Components\Events\WorkflowTriggersComponent;
use Phalcon\Cli\Task;

/**
 * Workflow Task
 *
 * `php cli.php workflow schedule` fires the schedule triggers due this
 * minute (cron expressions are read in UTC). Run it once a minute, e.g.
 * from cron: `* * * * * cd /var/www/html && php cli.php workflow schedule`.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Core\Tasks
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class WorkflowTask extends Task
{
    /**
     * Default action - shows help
     */
    public function mainAction()
    {
        echo 'Workflow Task - Available actions:' . PHP_EOL;
        echo '  schedule - Start the workflows whose schedule is due this minute' . PHP_EOL;
    }

    /**
     * Starts the workflows whose schedule trigger is due this minute. The
     * records their runs change can start other workflows.
     */
    public function scheduleAction()
    {
        $runner = new ScheduleTriggerRunner(null, new WorkflowRunner(new WorkflowTriggersComponent()));
        $started = $runner->run(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        echo "Scheduled workflow runs started: {$started}" . PHP_EOL;
    }
}
