<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Tools\Docker;

use PHPUnit\Framework\TestCase;

/**
 * The containers' crontab (agaia-4660): every job runs a Gaia CLI task and action that exist. The schedule syntax
 * is checked by `supercronic -test` in the image build; this catches a renamed or removed task, which it cannot.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class CrontabTest extends TestCase
{
    const CRONTAB = __DIR__ . '/../../../docker/cron/crontab';

    /**
     * The crontab's job lines: neither blank, a comment nor an environment assignment.
     *
     * @return array
     */
    private function jobLines()
    {
        $lines = array_map('trim', file(self::CRONTAB));

        return array_values(array_filter($lines, function ($line) {
            return $line !== '' && $line[0] !== '#' && !preg_match('/^\w+\s*=/', $line);
        }));
    }

    public function testCrontabHasJobs()
    {
        $this->assertNotEmpty($this->jobLines(), 'docker/cron/crontab has no job line');
    }

    public function testEveryJobRunsAnExistingTaskAction()
    {
        foreach ($this->jobLines() as $line) {
            $this->assertSame(1, preg_match('#\bcli\.php\s+(\w+)\s+(\w+)#', $line, $match), "not a Gaia CLI task: {$line}");
            $task = ucfirst($match[1]) . 'Task';
            $this->assertTrue(class_exists($task), "no task {$task}: {$line}");
            $this->assertTrue(method_exists($task, $match[2] . 'Action'), "no action {$task}::{$match[2]}Action: {$line}");
        }
    }
}
