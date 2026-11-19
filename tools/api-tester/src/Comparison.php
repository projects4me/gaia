<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace ApiTester;

/**
 * The differences ReportComparer found between two reports, and their
 * groupings for the summary.
 *
 * Each difference is an array: id (case id), endpoint (METHOD + catalog path),
 * kind (see ReportComparer::KIND_*), path (where in the body, `$` is the root,
 * empty for case-level differences), a and b (the two values, or a description).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package ApiTester
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Comparison
{
    private $differences;
    private $casesCompared;

    /**
     * @param array $differences
     * @param int $casesCompared cases present in both reports
     */
    public function __construct(array $differences, $casesCompared)
    {
        $this->differences = $differences;
        $this->casesCompared = $casesCompared;
    }

    /**
     * @return array
     */
    public function differences()
    {
        return $this->differences;
    }

    /**
     * @return int
     */
    public function casesCompared()
    {
        return $this->casesCompared;
    }

    /**
     * @return bool
     */
    public function isClean()
    {
        return empty($this->differences);
    }

    /**
     * @return array endpoint => differences, endpoints sorted
     */
    public function byEndpoint()
    {
        return $this->groupBy('endpoint');
    }

    /**
     * @return array kind => differences, kinds sorted
     */
    public function byKind()
    {
        return $this->groupBy('kind');
    }

    /**
     * @param string $field
     * @return array
     */
    private function groupBy($field)
    {
        $groups = [];
        foreach ($this->differences as $difference) {
            $groups[$difference[$field]][] = $difference;
        }
        ksort($groups);
        return $groups;
    }
}
