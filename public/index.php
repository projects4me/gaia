<?php

use Phalcon\Di,
    Phalcon\DI\FactoryDefault,
    Phalcon\Mvc\View,
    Phalcon\Mvc\Dispatcher,
    Phalcon\Mvc\Url as UrlResolver,
    Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter,
    Phalcon\Mvc\Model\Metadata\Memory as MetaDataAdapter,
    Phalcon\Session\Adapter\Files as SessionAdapter,
    Phalcon\Translate\Adapter\NativeArray,
    Phalcon\Logger,
    Phalcon\Logger\Adapter\File as FileAdapter,
    Gaia\Libraries\Utils\executiontime,
    Gaia\Libraries\Meta\Migration\Driver as migrationDriver;

/**
 * Error Reporting
 * @todo Remove before publishing
 */
error_reporting(E_ALL);
ini_set('display_errors',true);

// Setup the application constants
define('APP_PATH', realpath('..'));
define('DS', DIRECTORY_SEPARATOR);

require APP_PATH . '/autoload.php';

/**
 * @todo Need a more appropriate place for this
 */

global $logger;
$logger = new FileAdapter(APP_PATH.'/logs/application.log');
$logger->setLogLevel(Logger::DEBUG);

// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS, DELETE");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}


try {

    global $stime,$di,$apiVersion,$logger,$timer;
    $stime = explode(" ",microtime());
    $stime = $stime[1] + $stime[0];

    $timer = new executiontime();

    //Create a DI
    $di = new FactoryDefault();

    //Setup the view component
    $di->set('view', function(){
        $view = new \Phalcon\Mvc\View();
        $view->setViewsDir(APP_PATH.'/app/views/');
        $view->registerEngines(
            array(
                ".volt" => 'Phalcon\Mvc\View\Engine\Volt'
            )
        );
        return $view;
    });

    //Setup a base URI so that all generated URIs include the "tutorial" folder
    $di->set('url', function(){
        $url = new \Phalcon\Mvc\Url();
        $url->setBaseUri('/');
        return $url;
    });


    // @todo - set in di
    $config = new \Gaia\Libraries\Config();
    $config->init();

    // @todo - add actions route
    $di->set('router', function(){
        $router = new Gaia\MVC\Router();
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
        $dblogger = new \Phalcon\Logger\Adapter\File(APP_PATH . "/db.log");
//         print_r($logger);
        //Listen all the database events
        $eventsManager->attach('db', function($event, $connection) use ($dblogger) {
//            global $logger;
            if ($event->getType() == 'beforeQuery') {
                $GLOBALS['timer']->diff();
                $sqlVariables = $connection->getSQLVariables();
                if (count($sqlVariables)) {
                    $dblogger->log(print_r($connection->getSQLBindTypes(),1) . ' ' . join(', ', $sqlVariables), \Phalcon\Logger::INFO);
                } else {
                    $dblogger->log(print_r($connection->getSQLBindTypes(),1), \Phalcon\Logger::INFO);
                }
            }
            if ($event->getType() == 'afterQuery') {
                $dblogger->log('Query execution time:'.($GLOBALS['timer']->diff()).' seconds', \Phalcon\Logger::INFO);
                $dblogger->log($connection->getSQLStatement(), \Phalcon\Logger::INFO);
            }
        });

         //Assign the eventsManager to the db adapter instance
         $connection->setEventsManager($eventsManager);
        return $connection;
    });

    /**
     * @todo move the migration away to elsewhere
     */
    migrationDriver::migrate();

    //Handle the request
    $app = new \Phalcon\Mvc\Application($di);
    echo $app->handle()->getContent();

} catch(\Phalcon\Exception $e) {
     echo "PhalconException: ", $e->getMessage();
}


