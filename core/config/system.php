<?php

// Get permission flags.
$filePath = APP_PATH . '/app/metadata/model/Permission.php';
$fields = array_keys(($this->di->get('fileHandler')->readFile($filePath))['Permission']['fields']);
$permissionFlags = array_values(
    array_filter(
        $fields, function ($field) {
            return strpos($field, 'F') !== false;
        }
    )
);

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
            'permissionFlags' => $permissionFlags,
            'apiOptions' => [
                'field' => [
                    'allow' => '9',
                    'none' => '0'
                ],
                'model' => [
                    'all' => '9',
                    'assignment' => '1',
                    'none' => '0'
                ],
                'groups' => [
                    'project' => '2',
                    'conversation' => '3'
                ]
            ],
            'modelGroups' => $modelGroups
        ]
    ];

return $config;
