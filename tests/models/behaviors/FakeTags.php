<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Gaia\Libraries\Tags\Tags;

/**
 * In-memory {@see Tags} for the tag behaviour tests.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FakeTags extends Tags
{
    /**
     * @var array[] Live tags: ['id' => ..., 'projectId' => ..., 'tag' => ...]
     */
    public $tags = [];

    /**
     * @var string[] Project of each live record, keyed "relatedTo:relatedId"
     */
    public $records = [];

    /**
     * @var string[] Applied taggings, as "tagId:relatedTo:relatedId"
     */
    public $applied = [];

    public function nameTaken($projectId, $name, $exceptTagId = null)
    {
        foreach ($this->tags as $tag) {
            if ($tag['projectId'] === $projectId && $tag['id'] !== $exceptTagId
                && mb_strtolower($tag['tag']) === mb_strtolower($name)
            ) {
                return true;
            }
        }
        return false;
    }

    public function projectOfTag($tagId)
    {
        foreach ($this->tags as $tag) {
            if ($tag['id'] === $tagId) {
                return $tag['projectId'];
            }
        }
        return null;
    }

    public function projectOfRecord($relatedTo, $relatedId)
    {
        return $this->records[$relatedTo . ':' . $relatedId] ?? null;
    }

    public function isApplied($tagId, $relatedTo, $relatedId)
    {
        return in_array($tagId . ':' . $relatedTo . ':' . $relatedId, $this->applied, true);
    }
}
