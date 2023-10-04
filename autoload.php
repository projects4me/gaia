<?php

/**
 * This file is used to load some of the basic files required by the
 * application and at the same time the file also sets up the auto-loading
 * for the application that follows a certain pattern
 *
 * The assets to be loaded are:
 *
 * * Core Controllers & components.
 * * Core Models and Behaviors.
 * * Core Libraries.
 * * Core Config
 * * Application Controllers.
 * * Application Models.
 * * Application Routes
 * * Application Config
 * * Custom Controllers
 * * Custom Models
 * * Custom Routes
 */

require APP_PATH.'/vendor/autoload.php';

require_once(APP_PATH.'/core/mvc/models/behaviors/auditBehavior.php');

require_once APP_PATH.'/core/libs/file/handler.php';

require_once APP_PATH.'/core/libs/meta/manager.php';
require_once APP_PATH.'/core/libs/meta/migration.php';
require_once(APP_PATH.'/core/libs/meta/migration/driver.php');

require_once APP_PATH.'/core/libs/config.php';

require_once APP_PATH.'/core/libs/utils/executiontime.php';
require_once(APP_PATH.'/core/libs/utils/utility_functions.php');
require_once APP_PATH.'/core/libs/utils/Util.php';

require_once(APP_PATH.'/core/libs/security/acl.php');

require_once(APP_PATH.'/core/libs/oauth2.0/storage/pdo.php');

$request = new \Phalcon\Http\Request();
$appVersions = include(APP_PATH.'/version.php');
$apiVersion = $appVersions['apiVersion'];
// if api version is available in the request then load it
/**
 * @todo add more validation
 * @todo move over to config
 */
if (preg_match('@api/?(v[^/]+)@',$request->getURI(),$matches))
{
    $apiVersion = $matches[1];
}

//Register an autoloader
$loader = new \Phalcon\Loader();

$loader->registerNamespaces(
    [
        "Gaia\\MVC\\REST\\Controllers" => APP_PATH.'/app/api/'.$apiVersion.'/controllers/',
        "Gaia\\MVC\\REST\\Controllers\\Components" => APP_PATH.'/app/api/'.$apiVersion.'/controllers/components/',
        "Gaia\\MVC\\Models\\Behaviors" => APP_PATH.'/core/mvc/models/behaviors/',
        "Gaia\\MVC\\Models" => APP_PATH. '/app/models/',
        "Gaia\\Db\\Factory" => APP_PATH. '/core/mvc/db/factory/',
        "Gaia\\Db\\Dialect" => APP_PATH. '/core/mvc/db/dialect/',
        "Gaia\\Core\\MVC\\Models" => APP_PATH. '/core/mvc/models/',
        "Gaia\\Core\\MVC\\Models\\Relationships" => APP_PATH. '/core/mvc/models/relationships',
        "Gaia\\Core\\MVC\\REST\\Controllers" => APP_PATH. '/core/mvc/controllers/',
        "Gaia\\Core\\MVC\\Models\\Relationships\\Factory" => APP_PATH. '/core/mvc/models/relationships/factory',
        "Gaia\\Core\\MVC\\Models\\Query" => APP_PATH. '/core/mvc/models/query/'
    ]
);

$loader->registerClasses(
    [
        'Gaia\\MVC\\Router' => APP_PATH.'/core/mvc/router.php',
    ]
);

$loader->registerDirs(
    [
        __DIR__ . '/core/tasks',
    ]
);

$loader->register();