<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

/*
 * Service clients (Serviceclient): the kinds of service that may sign in with
 * client_credentials, and the scopes each kind's client gets when it is
 * created without its own list. Every kind gets serviceclient.rotate, so
 * each service can rotate its own secret. See docs/architecture/rbac/decisions.md
 * (D-062, D-063, D-067).
 */
$config['serviceClients'] = array(
    'kinds' => array(
        // A Talos runtime: acts for the agents it runs and reads their work.
        'runtime' => array(
            'scopes' => array('agent.delegate', 'agent.read', 'project.read', 'issue.read', 'serviceclient.rotate'),
        ),
        // The Hermes relay: checks the tokens of the sockets it serves.
        'hermes' => array(
            'scopes' => array('token.introspect', 'serviceclient.rotate'),
        ),
    ),
);

return $config;
