<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Upgrade;

/**
 * Renders the next-stack findings as one markdown summary for $GITHUB_STEP_SUMMARY: a table with a count
 * and the artifact per finding, then the details of each finding in a collapsed block.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Upgrade
 * @category Tools
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class NextStackSummary
{
    /**
     * Lines listed per finding before the rest is left to the artifact
     */
    const MAX_LINES = 50;

    /**
     * @var array[] title, artifact, finding
     */
    private $rows = [];

    /**
     * @param string $title
     * @param string $artifact where the full output is
     * @param array $finding from NextStackFindings
     * @return $this
     */
    public function add($title, $artifact, array $finding)
    {
        $this->rows[] = ['title' => $title, 'artifact' => $artifact, 'finding' => $finding];
        return $this;
    }

    /**
     * @return string markdown
     */
    public function render()
    {
        $out = "# PHP 8.5 / Phalcon 5.23 (next stack)\n\n";
        $out .= "Non-blocking: what still fails on the next stack, compared with the current one.\n\n";
        $out .= "| Finding | Count | Artifact |\n|---|---|---|\n";
        foreach ($this->rows as $row) {
            $f = $row['finding'];
            $count = $f['count'] === null ? 'n/a' : (string) $f['count'];
            if ($f['note'] !== '') {
                $count .= ' (' . $this->escape($f['note']) . ')';
            }
            $out .= "| {$row['title']} | {$count} | `{$row['artifact']}` |\n";
        }
        foreach ($this->rows as $row) {
            $lines = $row['finding']['lines'];
            if (!$lines) {
                continue;
            }
            $out .= "\n<details><summary>{$row['title']}</summary>\n\n```\n";
            $out .= implode("\n", array_map([$this, 'fence'], array_slice($lines, 0, self::MAX_LINES))) . "\n";
            if (count($lines) > self::MAX_LINES) {
                $out .= '... ' . (count($lines) - self::MAX_LINES) . " more in {$row['artifact']}\n";
            }
            $out .= "```\n\n</details>\n";
        }
        return $out;
    }

    /**
     * @param string $text
     * @return string text safe inside a table cell
     */
    private function escape($text)
    {
        return str_replace(['|', "\n"], ['\|', ' '], $text);
    }

    /**
     * @param string $line
     * @return string line that cannot close the code fence
     */
    private function fence($line)
    {
        return str_replace('```', "'''", $line);
    }
}
