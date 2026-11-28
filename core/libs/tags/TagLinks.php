<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Tags;

/**
 * Writes the taggings (Tagged rows) of a whole tag at once, for a tag merge
 * or delete (Tags D-003). A tagging can't be changed through the API
 * (taggedBehavior), so these are bound SQL statements, not model saves; they
 * join the caller's transaction when there is one.
 *
 * @author  Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\Libraries\Tags
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TagLinks
{
    /**
     * Move every tagging of the source tag to the target tag. A record that
     * already has the target, or has the source more than once, keeps one
     * tagging; the source's other taggings are removed.
     *
     * @param  string $sourceTagId
     * @param  string $targetTagId
     * @return bool
     */
    public function moveAll($sourceTagId, $targetTagId)
    {
        $source = (string) $sourceTagId;
        $target = (string) $targetTagId;

        $moved = $this->db()->execute(
            'UPDATE tagged SET "tagId" = ? WHERE "tagId" = ?'
            . ' AND NOT EXISTS (SELECT 1 FROM tagged t WHERE t."tagId" = ?'
            . ' AND t."relatedTo" = tagged."relatedTo" AND t."relatedId" = tagged."relatedId")'
            . ' AND id = (SELECT MIN(s.id) FROM tagged s WHERE s."tagId" = ?'
            . ' AND s."relatedTo" = tagged."relatedTo" AND s."relatedId" = tagged."relatedId")',
            [$target, $source, $target, $source]
        );

        return (bool) $moved && $this->removeAll($source);
    }

    /**
     * Remove every tagging of the tag.
     *
     * @param  string $tagId
     * @return bool
     */
    public function removeAll($tagId)
    {
        return (bool) $this->db()->execute('DELETE FROM tagged WHERE "tagId" = ?', [(string) $tagId]);
    }

    /**
     * The database connection the taggings are written through.
     *
     * @return \Phalcon\Db\Adapter\AdapterInterface
     */
    protected function db()
    {
        return \Phalcon\Di\Di::getDefault()->getShared('db');
    }
}
