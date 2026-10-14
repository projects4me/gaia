<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/**
 * Application settings configuration.
 *
 * encryptionKey: 32 random bytes, base64 encoded, used to encrypt secure settings
 * (e.g. the SMTP password). Generate one with:
 *   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
 * Without it, secure settings cannot be saved.
 */
$config['settings'] = array(
    'encryptionKey' => getenv('SETTINGS_ENCRYPTION_KEY') ?: null,
);

return $config;
