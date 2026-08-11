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
        'resolutionMode' => 'permissive',
        'modelGroups' => $modelGroups,
        'moduleActions' => \Gaia\Libraries\Security\AclMapCatalog::buildModuleActions(),
        'moduleFields' => \Gaia\Libraries\Security\AclMapCatalog::buildModuleFields(
            $this->di->get('metaManager')
        ),
    ]
];

return $config;
