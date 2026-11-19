<?php

// Non-secret SMTP defaults. The server and credentials (host, username, password) live only in
// Settings > Email (see SmtpSettings); with no host saved, Mailer sends nothing.
$config['smtp'] = [
    'user' => 'Projects4me',
    'auth' => true,
    'port' => 587,
    'encryption' => 'tls',
    'resetTokenTimeout' => 3600 //seconds,
];

return $config;
