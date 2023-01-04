<?php
/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

defined('APP_PATH') || define('APP_PATH', realpath('.'));

// return new \Phalcon\Config(array(
//     'database' => array(
//         'adapter'     => 'Mysql',
//         'host'        => 'localhost',
//         'username'    => 'nouman',
//         'password'    => 'Rolus889',
//         'dbname'      => 'pr4m',
//         'charset'     => 'utf8',
//     ),
//     'application' => array(
//         'controllersDir' => APP_PATH . '/app/controllers/',
//         'modelsDir'      => APP_PATH . '/app/models/',
//         'migrationsDir'  => APP_PATH . '/app/migrations/',
//         'viewsDir'       => APP_PATH . '/app/views/',
//         'pluginsDir'     => APP_PATH . '/app/plugins/',
//         'libraryDir'     => APP_PATH . '/app/library/',
//         'cacheDir'       => APP_PATH . '/app/cache/',
//         'baseUri'        => '/htdocs/',
//     )
// ));


require_once APP_PATH.'/core/libs/config.php';
$config = new \Gaia\Libraries\Config();
$config->init();

return $GLOBALS['settings'];
