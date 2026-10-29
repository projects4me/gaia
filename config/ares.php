<?php

$config['ares'] = array(
    // Ares sends this in X-Ares-Secret to introspect agent tokens. Empty
    // disables introspection: there is no default secret.
    'introspectionSecret' => getenv('ARES_INTROSPECTION_SECRET') ?: '',
);

return $config;
