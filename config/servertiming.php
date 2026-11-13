<?php

// Server-Timing header on API responses. Off unless SERVER_TIMING=1 (dev and QA only: it must stay off in production).
$config['servertiming'] = array(
    'enabled' => getenv('SERVER_TIMING') === '1',
    'allowOrigin' => getenv('SERVER_TIMING_ALLOW_ORIGIN') ?: '',
);

return $config;
