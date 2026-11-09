<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/*
 * Read-only counts per conversation room, so a room list can show its comment, like and vote tallies in one
 * request instead of loading every room's comments.
 *
 * - commentCount: comments on the room that are not deleted (vote comments included).
 * - likeCount: up-votes on the room (votes.vote = 1).
 * - agreeCount / disagreeCount: comments with the exact text "I agree" / "I disagree" on rooms of type "vote";
 *   0 on other rooms.
 * - lastCommentDate: dateCreated of the newest comment that is not deleted; null when there is none.
 *
 * Deleted rooms have no row.
 */
$models['Conversationroomcount'] = array(
    'tableName' => 'conversation_room_counts',
    'viewSql' => 'SELECT "Room".id AS id, "Room"."projectId" AS "projectId",
                    (SELECT COUNT("Comment".id) FROM comments AS "Comment"
                        WHERE "Comment"."relatedTo" = \'conversationrooms\' AND "Comment"."relatedId" = "Room".id
                        AND "Comment".deleted = \'0\') AS "commentCount",
                    (SELECT COUNT("Vote".id) FROM votes AS "Vote"
                        WHERE "Vote"."relatedTo" = \'conversationrooms\' AND "Vote"."relatedId" = "Room".id
                        AND "Vote".vote = \'1\') AS "likeCount",
                    (SELECT COUNT("Comment".id) FROM comments AS "Comment"
                        WHERE "Room"."roomType" = \'vote\'
                        AND "Comment"."relatedTo" = \'conversationrooms\' AND "Comment"."relatedId" = "Room".id
                        AND "Comment".deleted = \'0\' AND "Comment".comment = \'I agree\') AS "agreeCount",
                    (SELECT COUNT("Comment".id) FROM comments AS "Comment"
                        WHERE "Room"."roomType" = \'vote\'
                        AND "Comment"."relatedTo" = \'conversationrooms\' AND "Comment"."relatedId" = "Room".id
                        AND "Comment".deleted = \'0\' AND "Comment".comment = \'I disagree\') AS "disagreeCount",
                    (SELECT MAX("Comment"."dateCreated") FROM comments AS "Comment"
                        WHERE "Comment"."relatedTo" = \'conversationrooms\' AND "Comment"."relatedId" = "Room".id
                        AND "Comment".deleted = \'0\') AS "lastCommentDate"
                  FROM conversation_rooms AS "Room"
                  WHERE "Room".deleted = \'0\'',
    'isView' => true,
    'fields' => array(
        'id' => array(
            'name' => 'id',
            'label' => 'LBL_CONVERSATION_ROOM_COUNTS_ID',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'identifier' => true
        ),
        'projectId' => array(
            'name' => 'projectId',
            'label' => 'LBL_CONVERSATION_ROOM_COUNTS_PROJECT',
            'type' => 'varchar',
            'length' => '36',
            'null' => false,
            'relatedIdentifier' => true
        ),
        'commentCount' => array(
            'name' => 'commentCount',
            'label' => 'LBL_CONVERSATION_ROOM_COUNTS_COMMENT_COUNT',
            'type' => 'int',
            'null' => false,
        ),
        'likeCount' => array(
            'name' => 'likeCount',
            'label' => 'LBL_CONVERSATION_ROOM_COUNTS_LIKE_COUNT',
            'type' => 'int',
            'null' => false,
        ),
        'agreeCount' => array(
            'name' => 'agreeCount',
            'label' => 'LBL_CONVERSATION_ROOM_COUNTS_AGREE_COUNT',
            'type' => 'int',
            'null' => false,
        ),
        'disagreeCount' => array(
            'name' => 'disagreeCount',
            'label' => 'LBL_CONVERSATION_ROOM_COUNTS_DISAGREE_COUNT',
            'type' => 'int',
            'null' => false,
        ),
        'lastCommentDate' => array(
            'name' => 'lastCommentDate',
            'label' => 'LBL_CONVERSATION_ROOM_COUNTS_LAST_COMMENT_DATE',
            'type' => 'datetime',
            'null' => true,
        ),
    ),
    'indexes' => array(
        'id' => 'primary',
    ),
    'foriegnKeys' => array(),
    'triggers' => array(),
    'functions' => array(),
    // Reading counts needs conversationroomcount.get plus project.get and conversationroom.get (D-028);
    // the members scope of project.get limits rows to the user's projects (D-060).
    'acl' => [
        'groups' => ['Project', 'Conversationroom'],
        'groupKeys' => [
            'Project' => 'projectId',
        ],
    ],
);

return $models;
