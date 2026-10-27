<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * What the issue type migration changes: which type names become values and
 * which values get an en-us label from the old name.
 *
 * Issue type names used to be display text (Change Request); they become
 * values (change_request) whose labels are Translation rows (ValueLabels).
 * The plan only reads the rows it is given, so running the migration again
 * finds nothing left to do: names that are already values are not renamed,
 * and values that already have an en-us label keep it.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Utils
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class IssueTypeValuePlan
{
    /**
     * id => value, for the types whose name changes.
     *
     * @var array
     */
    private $renames = [];

    /**
     * value => en-us label, for the values a live type uses that have no label.
     *
     * @var array
     */
    private $labels = [];

    /**
     * Ids of the types whose name has nothing a value can keep.
     *
     * @var string[]
     */
    private $skipped = [];

    /**
     * @param array    $types           Rows with id, name, system and deleted.
     * @param string[] $labelledValues  Values that already have an en-us label.
     */
    public function __construct(array $types, array $labelledValues)
    {
        $labelled = array_fill_keys(array_map('strval', $labelledValues), true);

        // A live system type's name is the best label for its value, so it goes first.
        usort($types, function ($a, $b) {
            return (int) $b['system'] - (int) $a['system'];
        });

        foreach ($types as $type) {
            $name = (string) $type['name'];
            $value = self::valueFor($name);

            if ($value === '') {
                $this->skipped[] = (string) $type['id'];
                continue;
            }
            if ($value !== $name) {
                $this->renames[(string) $type['id']] = $value;
            }
            if (!(int) $type['deleted'] && !isset($labelled[$value]) && !isset($this->labels[$value])) {
                $this->labels[$value] = $name;
            }
        }
    }

    /**
     * The value of a type name: lowercase, spaces become underscores, only
     * [a-z0-9_] is kept, at most ValueLabelRules::VALUE_MAX_LENGTH characters.
     *
     * @param  string $name
     * @return string Empty when nothing of the name can be kept.
     */
    public static function valueFor($name)
    {
        $value = preg_replace('/\s+/', '_', trim(strtolower((string) $name)));
        $value = preg_replace('/[^a-z0-9_]/', '', $value);

        return substr($value, 0, ValueLabelRules::VALUE_MAX_LENGTH);
    }

    /**
     * @return array id => new name
     */
    public function renames()
    {
        return $this->renames;
    }

    /**
     * @return array value => en-us label
     */
    public function labels()
    {
        return $this->labels;
    }

    /**
     * @return string[] Ids of the types left as they are.
     */
    public function skipped()
    {
        return $this->skipped;
    }
}
