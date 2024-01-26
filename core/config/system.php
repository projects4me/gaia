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

// This contains the system settings.
$config['system'] = [
    'acl' => [
            'permissionFlags' => $permissionFlags,
            'apiOptions' => [
                'all' => '9',
                'group' => '2',
                'assignment' => '1',
                'none' => '0'
            ]
        ]
    ];

return $config;
