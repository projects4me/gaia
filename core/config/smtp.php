<?php

// SMTP configuration for Gmail
$config['smtp'] = [
    'host' => 'smtp.gmail.com',
    'user' => 'Projects4me',
    'auth' => true,
    'port' => 587,
    'username' => getenv('SMTP_USERNAME'),
    'password' => getenv('SMTP_PASSWORD')
];

return $config;
