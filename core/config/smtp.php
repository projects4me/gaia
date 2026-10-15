<?php

// SMTP configuration. Defaults to Gmail; SMTP_HOST / SMTP_PORT / SMTP_ENCRYPTION override it
// (the test services point it at an unreachable host, so default runs never reach a real mail server).
$config['smtp'] = [
    'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'user' => 'Projects4me',
    'auth' => true,
    'port' => (int) (getenv('SMTP_PORT') ?: 587),
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
    'username' => getenv('SMTP_USERNAME'),
    'password' => getenv('SMTP_PASSWORD'),
    'resetTokenTimeout' => 3600 //seconds,
];

return $config;
