<?php

/**
 * PostgreSQL adapter connection settings (merged by config/database.php).
 */

return array(
    'connectionClass' => \Phalcon\Db\Adapter\Pdo\Postgresql::class,
    'connectionKeys' => array('host', 'username', 'password', 'dbname', 'port', 'schema', 'options'),
    // Created by migrate (CREATE EXTENSION IF NOT EXISTS) for vector columns and trigram indexes
    'extensions' => array('vector', 'pg_trgm'),
);
