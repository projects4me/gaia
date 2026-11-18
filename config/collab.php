<?php

$config['collab'] = array(
    'url' => getenv('COLLAB_URL') ?: 'http://host.docker.internal:9100',
    'secret' => getenv('COLLAB_SECRET') ?: '',
);

return $config;
