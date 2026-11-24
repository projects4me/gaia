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
        'fuzzyThreshold' => \Gaia\Libraries\Search\FuzzySearcher::DEFAULT_THRESHOLD,
        // Cosine distance a semantic search match may have at most, from 0 to 2.
        'semanticMaxDistance' => \Gaia\Libraries\Search\SemanticSearcher::DEFAULT_MAX_DISTANCE,
        // Score from 0 to 1 a result needs to be kept; exact matches are always kept.
        'threshold' => \Gaia\Libraries\Search\StrongestSignal::DEFAULT_THRESHOLD,
        // Score from 0 to 1 of a perfect fuzzy match.
        'fuzzyWeight' => \Gaia\Libraries\Search\StrongestSignal::DEFAULT_FUZZY_WEIGHT,
        // Cosine distances a semantic match scores 1 at (or nearer) and 0 at (or farther), from 0 to 2,
        // calibrated for the embedding model: recalibrate them when it changes.
        'semanticNear' => \Gaia\Libraries\Search\StrongestSignal::DEFAULT_SEMANTIC_NEAR,
        'semanticFar' => \Gaia\Libraries\Search\StrongestSignal::DEFAULT_SEMANTIC_FAR,
    ],
];

return $config;
