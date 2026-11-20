<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Quality;

/**
 * Renders a DeprecationGrouper as markdown, suitable for a CI job summary.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Quality
 * @category Tools
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class MarkdownReport
{
    /**
     * @param DeprecationGrouper $grouper
     * @return string
     */
    public function render(DeprecationGrouper $grouper)
    {
        $t = $grouper->totals();
        $out = "# PHP deprecations and warnings\n\n";
        $out .= "{$t['total']} entries: {$t['gaia']} in Gaia code, {$t['vendor']} in vendor.\n";
        foreach (['gaia' => 'Gaia code', 'vendor' => 'Vendor'] as $origin => $title) {
            $out .= "\n## {$title}\n\n";
            $groups = $grouper->byPattern($origin);
            if (!$groups) {
                $out .= "None.\n";
                continue;
            }
            foreach ($groups as $g) {
                $out .= "### {$g['level']} x{$g['count']}: " . $this->escape($g['pattern']) . "\n\n";
                $out .= "Example: `" . $this->escape($g['example']) . "`\n\n";
                $out .= "| Location | Count |\n|---|---|\n";
                foreach ($g['locations'] as $location => $count) {
                    $out .= "| {$location} | {$count} |\n";
                }
                $out .= "\n";
            }
        }
        return $out;
    }

    /**
     * @param string $text
     * @return string text safe inside a table cell or inline code
     */
    private function escape($text)
    {
        return str_replace(['`', '|', "\n"], ["'", '\|', ' '], $text);
    }
}
