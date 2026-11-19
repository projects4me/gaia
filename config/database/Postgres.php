<?php

/**
 * PostgreSQL adapter connection settings (merged by config/database.php).
 */

return array(
    'connectionClass' => \Phalcon\Db\Adapter\Pdo\Postgresql::class,
    'connectionKeys' => array('host', 'username', 'password', 'dbname', 'port', 'schema', 'options'),
    // Created by migrate (CREATE EXTENSION IF NOT EXISTS) for vector columns, trigram indexes
    // and uuid-ossp, which the views need (an empty database has none)
    'extensions' => array('vector', 'pg_trgm', 'uuid-ossp'),
);
