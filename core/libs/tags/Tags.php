<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Tags;

use Gaia\MVC\Models\Conversationroom;
use Gaia\MVC\Models\Doc;
use Gaia\MVC\Models\Issue;
use Gaia\MVC\Models\Tag;
use Gaia\MVC\Models\Tagged;
use Gaia\MVC\Models\Wiki;

/**
 * Reads tags and taggings for the tag and tagged behaviours. All queries use
 * bound parameters and see only live rows.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Tags
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Tags
{
    /**
     * The records a tag can be applied to, by Tagged.relatedTo. Each has a projectId.
     */
    const TAGGABLE = [
        'issue' => Issue::class,
        'conversationroom' => Conversationroom::class,
        'wiki' => Wiki::class,
        'doc' => Doc::class,
    ];

    /**
     * Whether another live tag of the project has the name, compared case-insensitively.
     *
     * @param  string      $projectId
     * @param  string      $name
     * @param  string|null $exceptTagId The tag being saved
     * @return bool
     */
    public function nameTaken($projectId, $name, $exceptTagId = null)
    {
        $conditions = 'projectId = :projectId: AND LOWER(tag) = LOWER(:tag:) AND deleted = :deleted:';
        $bind = ['projectId' => (string) $projectId, 'tag' => (string) $name, 'deleted' => 0];
        if ($exceptTagId !== null && $exceptTagId !== '') {
            $conditions .= ' AND id <> :id:';
            $bind['id'] = (string) $exceptTagId;
        }

        return Tag::count(['conditions' => $conditions, 'bind' => $bind]) > 0;
    }

    /**
     * The live tag with the id, or null when it is unknown or deleted.
     *
     * @param  string $tagId
     * @return Tag|null
     */
    public function findLive($tagId)
    {
        $tag = Tag::findFirst([
            'conditions' => 'id = :id: AND deleted = :deleted:',
            'bind' => ['id' => (string) $tagId, 'deleted' => 0],
        ]);

        return $tag ?: null;
    }

    /**
     * The project of a live tag, or null when the tag is unknown, deleted or has no project.
     *
     * @param  string $tagId
     * @return string|null
     */
    public function projectOfTag($tagId)
    {
        $tag = Tag::findFirst([
            'conditions' => 'id = :id: AND deleted = :deleted:',
            'bind' => ['id' => (string) $tagId, 'deleted' => 0],
            'columns' => 'projectId',
        ]);

        return $tag && (string) $tag->projectId !== '' ? (string) $tag->projectId : null;
    }

    /**
     * The project of a live taggable record, or null when the type can't be
     * tagged or the record is unknown or deleted.
     *
     * @param  string $relatedTo
     * @param  string $relatedId
     * @return string|null
     */
    public function projectOfRecord($relatedTo, $relatedId)
    {
        $model = self::TAGGABLE[(string) $relatedTo] ?? null;
        if ($model === null) {
            return null;
        }

        $record = $model::findFirst([
            'conditions' => 'id = :id: AND deleted = :deleted:',
            'bind' => ['id' => (string) $relatedId, 'deleted' => 0],
            'columns' => 'projectId',
        ]);

        return $record && (string) $record->projectId !== '' ? (string) $record->projectId : null;
    }

    /**
     * Whether the tag is already applied to the record.
     *
     * @param  string $tagId
     * @param  string $relatedTo
     * @param  string $relatedId
     * @return bool
     */
    public function isApplied($tagId, $relatedTo, $relatedId)
    {
        return Tagged::count([
            'conditions' => 'tagId = :tagId: AND relatedTo = :relatedTo: AND relatedId = :relatedId:',
            'bind' => [
                'tagId' => (string) $tagId,
                'relatedTo' => (string) $relatedTo,
                'relatedId' => (string) $relatedId,
            ],
        ]) > 0;
    }
}
