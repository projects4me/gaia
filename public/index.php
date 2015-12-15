<?php
use Phalcon\DI\FactoryDefault,
    Phalcon\Mvc\View,
    Phalcon\Mvc\Dispatcher,
    Phalcon\Mvc\Url as UrlResolver,
    Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
    Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter,
    Phalcon\Session\Adapter\Files as SessionAdapter,
    Phalcon\Translate\Adapter\NativeArray,
    Phalcon\Logger\Adapter\File;

error_reporting(E_ALL);
define('APP_PATH', realpath('..'));
ini_set('display_errors',true);
require '../foundation/controllers/component.php';
require '../foundation/controllers/components/acl.php';
require '../foundation/controllers/components/auditable.php';
    
try {
    
    global $stime,$di,$apiVersion,$logger;
    $stime = explode(" ",microtime());
    $stime = $stime[1] + $stime[0];
    
    $request = new \Phalcon\Http\Request();
    $appVersions = include('../version.php'); 
    $apiVersion = $appVersions['apiVersion'];
    // if api version is available in the request then load it
    /**
     * @todo add more validation
     */
    if (preg_match('@api/?(v[^/]+)@',$request->getURI(),$matches))
    {
        $apiVersion = $matches[1];
    }

    //Register an autoloader
    $loader = new \Phalcon\Loader();
    $loader->registerDirs(array(
        APP_PATH.'/foundation/controllers/',
        APP_PATH.'/foundation/libs/',
        APP_PATH.'/app/api/'.$apiVersion.'/controllers/',
        APP_PATH.'/app/models/',
        APP_PATH.'/config/',        
        APP_PATH.'/vendor/',        
    ))->register();

    /*
    print "<pre>";
    $regDirs = $loader->getDirs();
    print_r($regDirs);
    
    $loader->registerDirs(array(
        APP_PATH.'/app/models/v1/',
    ))->register();

    $classes = $loader->getClasses();
    print "<pre>";
    print_r($loader);
    print "</pre>";
*/
    require_once(APP_PATH.'/foundation/libs/utility_functions.php');
    require_once(APP_PATH.'/foundation/controllers/RestController.php');
    //Create a DI
    $di = new Phalcon\DI\FactoryDefault();

    //Setup the view component
    $di->set('view', function(){
        $view = new \Phalcon\Mvc\View();
        $view->setViewsDir(APP_PATH.'/app/views/');
        return $view;
    });

    //Setup a base URI so that all generated URIs include the "tutorial" folder
    $di->set('url', function(){
        $url = new \Phalcon\Mvc\Url();
        $url->setBaseUri('/');
        return $url;
    });
    
    require_once APP_PATH.'/foundation/models/model.php';
    require_once APP_PATH.'/foundation/libs/fileHandler.php';

    require_once APP_PATH.'/foundation/libs/metaManager.php';
    require_once APP_PATH.'/foundation/libs/migration.php';
    
    // @todo - set in di
    require_once APP_PATH.'/foundation/libs/config.php';
    $config = new \Foundation\Config();
    $config->init();
    
    // @todo - add actions route
    $di->set('router', function(){
        require_once APP_PATH.'/foundation/mvc/router.php';
        $router = new Foundation\Mvc\Router();
        $router->init();
//        print "<pre>";
//        print_r($router);
//        print "</pre>";die();
        return $router;
    });

    
    // Set up the database service
    $di->set('db', function () {
 //       global $logger;
        $connection = new DbAdapter((array) $GLOBALS['settings']['database']);
        
        $eventsManager = new Phalcon\Events\Manager();

        $logger = new \Phalcon\Logger\Adapter\File(APP_PATH . "/db.log");
//         print_r($logger);
        //Listen all the database events
        $eventsManager->attach('db', function($event, $connection) use ($logger) {
//            global $logger;
            if ($event->getType() == 'beforeQuery') {
                $sqlVariables = $connection->getSQLVariables();
                if (count($sqlVariables)) {
                    $logger->log($connection->getSQLStatement() . ' ' . join(', ', $sqlVariables), \Phalcon\Logger::INFO);
                } else {
                    $logger->log($connection->getSQLStatement(), \Phalcon\Logger::INFO);
                }
            }
        });

         //Assign the eventsManager to the db adapter instance
         $connection->setEventsManager($eventsManager);
        return $connection;
    });

    /**
     * @todo move the migration away to elsewhere
     */
    require '../foundation/libs/migration/driver.php';
    Foundation\Mvc\Model\Migration\Driver::migrate();
    require '../foundation/libs/acl.php';

    //Handle the request
    $app = new \Phalcon\Mvc\Application($di);
    echo $app->handle()->getContent();

} catch(\Phalcon\Exception $e) {
     echo "PhalconException: ", $e->getMessage();
}

