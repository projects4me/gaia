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
                        'type' => 'text',
                        "copy_to" => "fulltext",
                        "analyzer" => "autocomplete",
                    ),
                    'issueNumber' => array(
                        'model' => 'issue.issueNumber',
                        'type' => 'integer'
                    ),
                ),
            ),
            'wiki' => array(
                'model' => 'wiki',
                'type' => 'nested',
                'properties' => array(
                    'subject' => array(
                        'model' => 'wiki.name',
                        'type' => 'text'
                    ),
                    'description' => array(
                        'model' => 'wiki.markUp',
                        'type' => 'text'
                    ),
                ),
            ),
            "fulltext" => array(
                "analyzer" => "autocomplete",
                //"search_analyzer" => "standard",
                "type" => "text"
            ),
        ),
    ),
);

return $mappings;
