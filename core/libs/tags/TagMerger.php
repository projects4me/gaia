<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Tags;

use Gaia\Libraries\Utils\TransactionScope;
use Gaia\MVC\Models\Tag;

/**
 * Merges a tag into another tag of the same project (Tags D-003): in one
 * transaction the source's taggings move to the target, without giving a
 * record the target twice, and the source tag is deleted.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Tags
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TagMerger
{
    const SOURCE_MISSING = 'The tag doesn\'t exist.';

    const TARGET_MISSING = 'The tag to merge into doesn\'t exist.';

    const SAME_TAG = 'A tag can\'t be merged into itself.';

    const OTHER_PROJECT = 'A tag can only be merged into a tag of the same project.';

    const NOT_DELETED = 'The merged tag could not be deleted.';

    /**
     * @var Tags
     */
    private $tags;

    /**
     * @var TagLinks
     */
    private $links;

    /**
     * @param Tags|null     $tags
     * @param TagLinks|null $links
     */
    public function __construct($tags = null, $links = null)
    {
        $this->tags = $tags ?: new Tags();
        $this->links = $links ?: new TagLinks();
    }

    /**
     * Merge the source tag into the target tag.
     *
     * @param  string $sourceTagId
     * @param  string $targetTagId
     * @return Tag The target tag
     * @throws TagMergeRefusal When either tag is missing, they are the same
     *                         tag or they belong to different projects
     */
    public function merge($sourceTagId, $targetTagId)
    {
        $source = $this->tags->findLive($sourceTagId);
        if ($source === null) {
            throw new TagMergeRefusal(self::SOURCE_MISSING, 404);
        }

        $target = $this->tags->findLive($targetTagId);
        if ($target === null) {
            throw new TagMergeRefusal(self::TARGET_MISSING, 422);
        }
        if ((string) $source->id === (string) $target->id) {
            throw new TagMergeRefusal(self::SAME_TAG, 422);
        }
        if ((string) $source->projectId === '' || (string) $source->projectId !== (string) $target->projectId) {
            throw new TagMergeRefusal(self::OTHER_PROJECT, 422);
        }

        $merged = $this->inTransaction($source, function () use ($source, $target) {
            return $this->links->moveAll($source->id, $target->id) && $source->delete();
        });
        if (!$merged) {
            $messages = $source->getMessages();
            throw new TagMergeRefusal($messages ? $messages[0]->getMessage() : self::NOT_DELETED, 422);
        }

        return $target;
    }

    /**
     * Run the work in one transaction on the tag's connection, kept only when
     * the work returns true.
     *
     * @param  Tag      $tag
     * @param  callable $work
     * @return bool
     * @throws \Throwable
     */
    protected function inTransaction($tag, callable $work)
    {
        return (bool) (new TransactionScope($tag->getWriteConnection()))->run($work, function ($done) {
            return (bool) $done;
        });
    }
}
