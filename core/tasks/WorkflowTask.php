<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

use Gaia\Libraries\Workflows\ActorSteps;
use Gaia\Libraries\Workflows\ActorStepTimeouts;
use Gaia\Libraries\Workflows\ScheduleTriggerRunner;
use Gaia\MVC\REST\Controllers\Components\Events\WorkflowTriggersComponent;
use Phalcon\Cli\Task;

/**
 * Workflow Task
 *
 * `php cli.php workflow schedule` fires the schedule triggers due this
 * minute (cron expressions are read in UTC). Run it once a minute, e.g.
 * from cron: `* * * * * cd /var/www/html && php cli.php workflow schedule`.
 * `php cli.php workflow timeouts` times out the 'Run agent' steps past their
 * deadline and retries answers that never reached the engine; run it once a
 * minute too.
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
        echo '  timeouts - Time out late agent steps and retry lost answers' . PHP_EOL;
    }

    /**
     * Starts the workflows whose schedule trigger is due this minute. The
     * records their runs change can start other workflows.
     */
    public function scheduleAction()
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $started = (new ScheduleTriggerRunner(null, (new WorkflowTriggersComponent())->runner()))->run($now);
        echo "Scheduled workflow runs started: {$started}" . PHP_EOL;
    }

    /**
     * Times out the 'Run agent' steps not claimed or finished in time, and
     * hands answers that never reached the engine to it again.
     */
    public function timeoutsAction()
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timeouts = new ActorStepTimeouts(new ActorSteps(), (new WorkflowTriggersComponent())->runner());
        echo 'Agent steps timed out: ' . $timeouts->run($now) . PHP_EOL;
        echo 'Lost answers retried: ' . $timeouts->retry($now) . PHP_EOL;
    }
}
