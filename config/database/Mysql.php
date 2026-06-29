<?php

/**
 * MySQL adapter connection settings (merged by config/database.php).
 */

return array(
    'charset' => 'utf8mb4',
    'options' => array(
        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ),
    'connectionClass' => \Phalcon\Db\Adapter\Pdo\Mysql::class,
    'connectionKeys' => array('host', 'username', 'password', 'dbname', 'port', 'charset', 'options'),
);
