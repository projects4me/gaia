<?php

$config['hermes'] = array(
    'url' => getenv('HERMES_URL') ?: 'http://host.docker.internal:9000',
    'secret' => getenv('HERMES_SECRET') ?: 'hermes-dev-secret',
);

return $config;
