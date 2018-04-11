<?php

/**
 * This file is used to load some of the basic files required by the
 * application and at the same time the file also sets up the auto-loading
 * for the application that follows a certain pattern
 *
 * The assets to be loaded are:
 *
 * * Foundation Controllers & components.
 * * Foundation Models and Behaviors.
 * * Foundation Libraries.
 * * Foundation Config
 * * Application Controllers.
 * * Application Models.
 * * Application Routes
 * * Application Config
 * * Custom Controllers
 * * Custom Models
 * * Custom Routes
 */

require APP_PATH.'/vendor/autoload.php';

require_once(APP_PATH.'/app/models/Behaviors/aclBehavior.php');
require_once(APP_PATH.'/foundation/mvc/models/behaviors/auditBehavior.php');

require_once APP_PATH.'/foundation/libs/file/handler.php';

require_once APP_PATH.'/foundation/libs/meta/manager.php';
require_once APP_PATH.'/foundation/libs/meta/migration.php';
require_once(APP_PATH.'/foundation/libs/meta/migration/driver.php');

require_once APP_PATH.'/foundation/libs/config.php';

require_once APP_PATH.'/foundation/libs/utils/executiontime.php';
require_once(APP_PATH.'/foundation/libs/utils/utility_functions.php');
require_once APP_PATH.'/foundation/libs/utils/Util.php';

require_once(APP_PATH.'/foundation/libs/security/acl.php');

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
        "Gaia\\MVC\\Models\\Behaviors" => APP_PATH.'/foundation/mvc/models/behaviors/',
        "Gaia\\MVC\\Models" => APP_PATH. '/app/models/'
    ]
);


//require_once(APP_PATH.'/foundation/mvc/controllers/components/auditable.php');

$loader->registerClasses(
    [
        'Gaia\\MVC\\REST\\Controllers\\Components\\Component' => APP_PATH.'/foundation/mvc/controllers/component.php',
        'Gaia\\MVC\\REST\\Controllers\\RestController' => APP_PATH.'/foundation/mvc/controllers/RestController.php',
        'Gaia\\MVC\\Models\\Model' => APP_PATH.'/foundation/mvc/models/model.php',
        'Gaia\\MVC\\Router' => APP_PATH.'/foundation/mvc/router.php',
    ]
);


$loader->register();