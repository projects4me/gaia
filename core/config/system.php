<?php

// Get groups for each model.
$models = $this->di->get('fileHandler')->readFile(APP_PATH . '/core/config/models.php');
$modelGroups = [];

// By now settings are not set as global variable. So we're just trying to populate the models
// array that will be used inside the metaManager getGroups() method.
global $settings;
$settings['models'] = $models['models'];

foreach ($models['models'] as $modelName) {
    $modelGroups[$modelName] = $this->di->get('metaManager')->getModelGroups($modelName);
}

// This contains the system settings.
$config['system'] = [
    'acl' => [
        'apiOptions' => [
            'allow' => '1',
            'none' => '0'
        ],
        // Numeric scopes for record-scoped resources (e.g. project.get).
        // 0 = none, 1 = all, 2 = members (membership-filtered).
        'scopedApiOptions' => [
            'all' => '1',
            'members' => '2',
            'none' => '0',
        ],
        'scopedResources' => [
            'project.get',
        ],
        'resolutionMode' => 'permissive',
        'modelGroups' => $modelGroups,
        'moduleActions' => \Gaia\Libraries\Security\AclMapCatalog::buildModuleActions(),
        'moduleFields' => \Gaia\Libraries\Security\AclMapCatalog::buildModuleFields(
            $this->di->get('metaManager')
        ),
    ],
    'search' => [
        // pg_trgm word_similarity a fuzzy search match needs, from 0 to 1.
        'fuzzyThreshold' => 0.3,
        // Cosine distance a semantic search match may have at most, from 0 to 2.
        'semanticMaxDistance' => 0.5,
        // Score from 0 to 1 a result needs to be kept; exact matches are always kept.
        'threshold' => 0.15,
        // Score from 0 to 1 of a perfect fuzzy match.
        'fuzzyWeight' => 0.6,
        // Cosine distances a semantic match scores 1 at (or nearer) and 0 at (or farther), from 0 to 2,
        // calibrated for the embedding model: recalibrate them when it changes.
        'semanticNear' => 0.156,
        'semanticFar' => 0.402,
        // For a query of several words, the score splitting the records matching every word (above it) from the
        // others (from the threshold up to it); past the threshold and under 1.
        'allWordsBoundary' => 0.5,
        // For a number query with no record of that number, the score splitting the numbers starting with it (above
        // it) from those ending with it (from the threshold up to it); past the threshold and under 1.
        'partialNumberBoundary' => 0.5,
        // The most words a query may have for its plain numbers to be looked up as record numbers (issue 4174);
        // in a longer query, pasted text, only numbers written with # are (see #3969).
        'numberQueryWords' => 5,
        // Candidates each searcher gives per type: the ranking needs more than the result limit to reorder.
        'candidateDepth' => 100,
        // Other forms of the query's words (abbreviations, spellings, split and joined words) proposed at most per
        // query; each is checked against the records once per type searched, so this bounds those lookups.
        'maxAlternatives' => 16,
        // Letters a query word needs before its British or American spelling is tried (colour: color), so short
        // words (door, tour) are left alone.
        'spellingMinLength' => 5,
        // Letters each half of a query word split into two needs (taskboard: task board).
        'splitMinPart' => 3,
        // Records a joined query word split into two (taskboard: task board) must have the pair in, to be used.
        'splitMinRecords' => 2,
    ],
];

return $config;
