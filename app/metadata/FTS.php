<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

$mappings = array(
// raw for aggregation
// filter.term project.user.id = currentUser
// version in the query to avoid conflicts?
// scroll and bulk to update all entries of the record

    'project' => array(
        'properties' => array(
            'name' => array(
                'model' => 'project',
                'type' => 'text'
            ),
            'issues' => array(
                'model' => 'issue',
                'type' => 'nested',
                'properties' => array(
                    'subject' => array(
                        'model' => 'issue.subject',
                        'type' => 'text'
                    ),
                    'issueNumber' => array(
                        'model' => 'issue.issueNumber',
                        'type' => 'integer'
                    ),
                ),
            ),
            'conversations' => array(
                'model' => 'conversationroom',
                'type' => 'nested',
                'properties' => array(
                    'subject' => array(
                        'model' => 'conversationroom.subject',
                        'type' => 'text'
                    ),
                    'description' => array(
                        'model' => 'converstaionroom.description',
                        'type' => 'text'
                    ),
                ),
            ),
        ),
    ),
);

return $mappings;
